<?php

declare(strict_types=1);

namespace ForgeStack\MediaAssets;

use ForgeStack\MediaAssets\Contracts\DerivativeGenerator;
use ForgeStack\MediaAssets\Contracts\MetadataExtractor;
use ForgeStack\MediaAssets\Contracts\StorageDriver;
use ForgeStack\MediaAssets\Drivers\Derivatives\ImageProcessor;
use ForgeStack\MediaAssets\Drivers\Derivatives\InterventionDerivativeGenerator;
use ForgeStack\MediaAssets\Drivers\Derivatives\InterventionImageProcessor;
use ForgeStack\MediaAssets\Drivers\Metadata\ExifToolMetadataExtractor;
use ForgeStack\MediaAssets\Drivers\Storage\LocalPublicStorageDriver;
use ForgeStack\MediaAssets\Drivers\Storage\S3StorageDriver;
use ForgeStack\MediaAssets\Support\ProductionStorageGuard;
use Illuminate\Support\ServiceProvider;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;

class MediaAssetsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/media-assets.php', 'media-assets');

        $this->bindStorage();
        $this->bindDerivatives();
        $this->bindMetadata();
    }

    public function boot(): void
    {
        ProductionStorageGuard::assertSafe($this->app);

        // Migrations are published, not auto-loaded: run
        // `vendor:publish --tag=media-assets-migrations` then `migrate`.
        // Auto-loading them here as well would register two files with
        // the same migration name once published, and Laravel resolves
        // that ambiguously depending on path registration order.
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/media-assets.php' => config_path('media-assets.php'),
            ], 'media-assets-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'media-assets-migrations');
        }

        // The package only emits events — listeners live in the host app.
    }

    private function bindStorage(): void
    {
        $this->app->bind(StorageDriver::class, function () {
            return match ((string) config('media-assets.storage_driver', 'local')) {
                's3' => new S3StorageDriver,
                'local' => new LocalPublicStorageDriver,
                default => new LocalPublicStorageDriver,
            };
        });
    }

    private function bindDerivatives(): void
    {
        // Singleton — the Imagick-vs-GD decision doesn't change per request.
        $this->app->singleton(ImageManager::class, function () {
            $driver = config('media-assets.driver') === 'imagick'
                ? ImagickDriver::class
                : GdDriver::class;

            return new ImageManager($driver);
        });

        $this->app->bind(ImageProcessor::class, InterventionImageProcessor::class);
        $this->app->bind(DerivativeGenerator::class, InterventionDerivativeGenerator::class);
    }

    private function bindMetadata(): void
    {
        $this->app->bind(MetadataExtractor::class, function () {
            return match ((string) config('media-assets.metadata_driver', 'exiftool')) {
                'exiftool' => new ExifToolMetadataExtractor(
                    binaryPath: (string) config('media-assets.exiftool_binary', 'exiftool'),
                    timeoutSeconds: (int) config('media-assets.metadata_timeout', 10),
                ),
                default => new ExifToolMetadataExtractor,
            };
        });
    }
}
