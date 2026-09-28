<?php

declare(strict_types=1);

namespace ForgeStack\MediaAssets\Events;

use ForgeStack\MediaAssets\Models\Asset;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when ProcessAssetJob exhausts its retries and the Asset is
 * marked Failed. The asset stays visible via urlFor() (falls back to
 * the original) — degraded UX, not broken.
 */
final class AssetProcessingFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Asset $asset,
        public readonly string $reason,
    ) {}
}
