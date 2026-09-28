<?php

declare(strict_types=1);

namespace ForgeStack\MediaAssets\Events;

use ForgeStack\MediaAssets\Models\Asset;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class AssetUploaded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Asset $asset,
    ) {}
}
