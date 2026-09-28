<?php

declare(strict_types=1);

namespace ForgeStack\MediaAssets\Events;

use ForgeStack\MediaAssets\Models\Asset;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when ProcessAssetJob finishes successfully and the Asset
 * transitions to Ready. Signals that every profile configured in
 * `generate_on_upload` is available.
 */
final class AssetAvailable
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Asset $asset,
    ) {}
}
