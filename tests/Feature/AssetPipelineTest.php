<?php

declare(strict_types=1);

namespace ForgeStack\MediaAssets\Tests\Feature;

use ForgeStack\MediaAssets\Actions\UploadAsset;
use ForgeStack\MediaAssets\Contracts\DerivativeGenerator;
use ForgeStack\MediaAssets\Contracts\MetadataExtractor;
use ForgeStack\MediaAssets\DTOs\AssetMetadata;
use ForgeStack\MediaAssets\Drivers\Derivatives\ImageProcessor;
use ForgeStack\MediaAssets\Enums\AssetStatus;
use ForgeStack\MediaAssets\Events\AssetAvailable;
use ForgeStack\MediaAssets\Events\AssetProcessingFailed;
use ForgeStack\MediaAssets\Events\AssetUploaded;
use ForgeStack\MediaAssets\Jobs\ProcessAssetJob;
use ForgeStack\MediaAssets\Models\Asset;
use ForgeStack\MediaAssets\Tests\TestCase;
use ForgeStack\MediaAssets\ValueObjects\ImageDimensions;
use ForgeStack\MediaAssets\ValueObjects\ImageProfile;
use ForgeStack\MediaAssets\ValueObjects\ProcessedImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

class AssetPipelineTest extends TestCase
{
    private function fakeImageProcessor(int $width = 800, int $height = 600): ImageProcessor
    {
        return new class($width, $height) implements ImageProcessor
        {
            public function __construct(private int $width, private int $height) {}

            public function process(string $sourcePath, ImageProfile $profile): ProcessedImage
            {
                return new ProcessedImage(
                    contents: 'fake-bytes',
                    dimensions: new ImageDimensions($this->width, $this->height),
                    format: $profile->format,
                    sizeBytes: 11,
                );
            }

            public function dimensions(string $sourcePath): ImageDimensions
            {
                return new ImageDimensions($this->width, $this->height);
            }
        };
    }

    private function fakeMetadataExtractor(?AssetMetadata $metadata = null): MetadataExtractor
    {
        return new class($metadata) implements MetadataExtractor
        {
            public function __construct(private ?AssetMetadata $metadata) {}

            public function extract(string $absolutePath): AssetMetadata
            {
                return $this->metadata ?? AssetMetadata::empty();
            }

            public function supports(string $mimeType): bool
            {
                return true;
            }

            public function name(): string
            {
                return 'fake';
            }
        };
    }

    private function makeAssetWithSource(): Asset
    {
        Storage::fake('public');

        $path = 'assets/test/original.jpg';
        Storage::disk('public')->put($path, 'source-bytes');

        return Asset::factory()->create([
            'disk' => 'public',
            'storage_path' => $path,
        ]);
    }

    public function test_image_profile_loads_from_config(): void
    {
        $profile = ImageProfile::named('thumbnail');

        $this->assertSame('thumbnail', $profile->name);
        $this->assertSame(320, $profile->maxWidth);
    }

    public function test_image_profile_rejects_invalid_quality(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ImageProfile('bad', 100, null, 150, 'jpg');
    }

    public function test_image_profile_rejects_unknown_format(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ImageProfile('bad', 100, null, 90, 'bmp');
    }

    public function test_image_profile_throws_when_undefined(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ImageProfile::named('does-not-exist');
    }

    public function test_derivative_generator_generates_and_persists(): void
    {
        $this->app->instance(ImageProcessor::class, $this->fakeImageProcessor());

        $asset = $this->makeAssetWithSource();

        $derivative = $this->app->make(DerivativeGenerator::class)->ensure($asset, 'thumbnail');

        $this->assertDatabaseHas('asset_derivatives', [
            'asset_id' => $asset->id,
            'profile' => 'thumbnail',
        ]);
        $this->assertSame(800, $derivative->width);
    }

    public function test_derivative_generator_is_idempotent(): void
    {
        $this->app->instance(ImageProcessor::class, $this->fakeImageProcessor());

        $asset = $this->makeAssetWithSource();
        $generator = $this->app->make(DerivativeGenerator::class);

        $first = $generator->ensure($asset, 'thumbnail');
        $second = $generator->ensure($asset, 'thumbnail');

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('asset_derivatives', 1);
    }

    public function test_derivative_generator_generates_all_configured_profiles(): void
    {
        $this->app->instance(ImageProcessor::class, $this->fakeImageProcessor());

        $asset = $this->makeAssetWithSource();
        $derivatives = $this->app->make(DerivativeGenerator::class)->generateAllForUpload($asset);

        $this->assertCount(4, $derivatives); // thumbnail, medium, large, web
    }

    public function test_derivative_generator_throws_when_source_missing(): void
    {
        $asset = Asset::factory()->create(['disk' => 'public', 'storage_path' => 'assets/missing.jpg']);

        $this->expectException(RuntimeException::class);

        $this->app->make(DerivativeGenerator::class)->ensure($asset, 'thumbnail');
    }

    public function test_asset_url_for_falls_back_to_original_without_derivative(): void
    {
        $asset = $this->makeAssetWithSource();

        $this->assertSame($asset->originalUrl(), $asset->urlFor('thumbnail'));
    }

    public function test_upload_asset_action_creates_asset_and_dispatches(): void
    {
        Storage::fake('public');
        Event::fake([AssetUploaded::class]);
        Bus::fake();

        $file = UploadedFile::fake()->image('photo.jpg', 100, 100);

        $asset = $this->app->make(UploadAsset::class)->execute($file);

        $this->assertSame(AssetStatus::Uploading, $asset->status);
        $this->assertSame('photo.jpg', $asset->original_filename);
        Event::assertDispatched(AssetUploaded::class);
        Bus::assertDispatched(ProcessAssetJob::class);
    }

    public function test_process_asset_job_moves_asset_to_ready(): void
    {
        $this->app->instance(ImageProcessor::class, $this->fakeImageProcessor());
        $this->app->instance(MetadataExtractor::class, $this->fakeMetadataExtractor());
        Event::fake([AssetAvailable::class]);

        $asset = $this->makeAssetWithSource();

        (new ProcessAssetJob($asset->id))->handle(
            $this->app->make(DerivativeGenerator::class),
            $this->app->make(MetadataExtractor::class),
        );

        $asset->refresh();
        $this->assertSame(AssetStatus::Ready, $asset->status);
        $this->assertDatabaseCount('asset_derivatives', 4);
        Event::assertDispatched(AssetAvailable::class);
    }

    public function test_process_asset_job_is_noop_if_asset_deleted(): void
    {
        $job = new ProcessAssetJob(999999);

        $job->handle(
            $this->app->make(DerivativeGenerator::class),
            $this->app->make(MetadataExtractor::class),
        );

        $this->assertTrue(true); // no exception = pass
    }

    public function test_process_asset_job_failed_marks_asset_failed(): void
    {
        Event::fake([AssetProcessingFailed::class]);

        $asset = $this->makeAssetWithSource();

        (new ProcessAssetJob($asset->id))->failed(new RuntimeException('boom'));

        $asset->refresh();
        $this->assertSame(AssetStatus::Failed, $asset->status);
        Event::assertDispatched(AssetProcessingFailed::class);
    }

    public function test_process_asset_job_persists_extracted_metadata(): void
    {
        $metadata = new AssetMetadata(dimensions: new ImageDimensions(1200, 900));

        $this->app->instance(ImageProcessor::class, $this->fakeImageProcessor());
        $this->app->instance(MetadataExtractor::class, $this->fakeMetadataExtractor($metadata));

        $asset = $this->makeAssetWithSource();

        (new ProcessAssetJob($asset->id))->handle(
            $this->app->make(DerivativeGenerator::class),
            $this->app->make(MetadataExtractor::class),
        );

        $asset->refresh();
        $this->assertSame(1200, $asset->width);
        $this->assertSame(900, $asset->height);
    }

    public function test_process_asset_job_does_not_break_when_extractor_throws(): void
    {
        $this->app->instance(ImageProcessor::class, $this->fakeImageProcessor());
        $this->app->bind(MetadataExtractor::class, function () {
            return new class implements MetadataExtractor
            {
                public function extract(string $absolutePath): AssetMetadata
                {
                    throw new RuntimeException('extractor exploded');
                }

                public function supports(string $mimeType): bool
                {
                    return true;
                }

                public function name(): string
                {
                    return 'broken';
                }
            };
        });

        $asset = $this->makeAssetWithSource();

        (new ProcessAssetJob($asset->id))->handle(
            $this->app->make(DerivativeGenerator::class),
            $this->app->make(MetadataExtractor::class),
        );

        $asset->refresh();
        $this->assertSame(AssetStatus::Ready, $asset->status);
    }

    public function test_asset_status_helpers(): void
    {
        $uploading = Asset::factory()->create(['status' => AssetStatus::Uploading]);
        $processing = Asset::factory()->create(['status' => AssetStatus::Processing]);
        $failed = Asset::factory()->create(['status' => AssetStatus::Failed]);

        $this->assertTrue($uploading->isProcessing());
        $this->assertTrue($processing->isProcessing());
        $this->assertFalse($failed->isProcessing());
        $this->assertTrue($failed->isFailed());
    }
}
