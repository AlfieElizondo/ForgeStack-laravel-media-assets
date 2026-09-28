<?php

declare(strict_types=1);

namespace ForgeStack\MediaAssets\Jobs;

use ForgeStack\MediaAssets\Contracts\DerivativeGenerator;
use ForgeStack\MediaAssets\Contracts\MetadataExtractor;
use ForgeStack\MediaAssets\Enums\AssetStatus;
use ForgeStack\MediaAssets\Events\AssetAvailable;
use ForgeStack\MediaAssets\Events\AssetProcessingFailed;
use ForgeStack\MediaAssets\Models\Asset;
use ForgeStack\MediaAssets\Support\StorageDriverFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Processes a freshly-uploaded Asset:
 *   1. Marks it Processing (if it was still Uploading).
 *   2. Extracts metadata (EXIF/ICC/GPS/dimensions/captured_at).
 *   3. Generates every derivative configured in
 *      `config('media-assets.generate_on_upload')`.
 *   4. Marks it Ready and dispatches AssetAvailable.
 *
 * On unrecoverable failure (after retries), marks it Failed and
 * dispatches AssetProcessingFailed. The asset stays displayable via
 * urlFor() -> falls back to the original.
 */
final class ProcessAssetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly int $assetId,
    ) {}

    public function handle(
        DerivativeGenerator $generator,
        MetadataExtractor $extractor,
    ): void {
        $asset = Asset::find($this->assetId);

        if (! $asset) {
            // Deleted while queued — not an error.
            return;
        }

        if ($asset->status === AssetStatus::Ready) {
            // Already processed — silent idempotency.
            return;
        }

        $asset->update(['status' => AssetStatus::Processing]);

        try {
            $this->extractMetadata($asset, $extractor);

            $generator->generateAllForUpload($asset);

            $asset->update(['status' => AssetStatus::Ready]);

            AssetAvailable::dispatch($asset->fresh());
        } catch (Throwable $e) {
            Log::warning('ProcessAssetJob failed', [
                'asset_id' => $asset->id,
                'attempt' => $this->attempts(),
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(?Throwable $e): void
    {
        $asset = Asset::find($this->assetId);

        if (! $asset) {
            return;
        }

        $asset->update(['status' => AssetStatus::Failed]);

        AssetProcessingFailed::dispatch(
            $asset,
            $e?->getMessage() ?? 'Unknown failure',
        );
    }

    /**
     * Extracts metadata from the original and persists:
     *   - Dedicated (queryable) columns: width, height, captured_at.
     *   - JSON metadata (presentational): camera, exposure, gps, ICC,
     *     color_space, extractor name.
     *
     * The extractor is defensive (never throws for files without EXIF);
     * but any unexpected exception here is logged and does NOT abort
     * the job — derivative generation still proceeds. A photo without
     * metadata is degraded UX, not a broken one.
     */
    private function extractMetadata(Asset $asset, MetadataExtractor $extractor): void
    {
        try {
            if ($asset->mime_type !== null && ! $extractor->supports($asset->mime_type)) {
                return;
            }

            $absolutePath = StorageDriverFactory::make($asset->disk)->absolutePath($asset->storage_path);
            $metadata = $extractor->extract($absolutePath);

            $existing = is_array($asset->metadata) ? $asset->metadata : [];
            $merged = array_merge($existing, $metadata->toJsonPayload());

            $asset->update([
                'width' => $metadata->dimensions?->width,
                'height' => $metadata->dimensions?->height,
                'captured_at' => $metadata->capturedAt,
                'metadata' => $merged,
            ]);
        } catch (Throwable $e) {
            Log::info('ProcessAssetJob: metadata extraction failed (non-fatal)', [
                'asset_id' => $asset->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
