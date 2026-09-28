<?php

declare(strict_types=1);

namespace ForgeStack\MediaAssets\Contracts;

use ForgeStack\MediaAssets\Models\Asset;
use ForgeStack\MediaAssets\Models\AssetDerivative;

/**
 * Generates derivatives (resized/re-encoded variants) of an Asset's
 * original file. V1 implements InterventionDerivativeGenerator (Imagick
 * or GD via Intervention Image v4).
 */
interface DerivativeGenerator
{
    /**
     * Generate (or regenerate, UPSERT) the derivative for the given
     * profile. Idempotent: calling this twice leaves exactly one
     * derivative per (asset_id, profile).
     */
    public function ensure(Asset $asset, string $profileName): AssetDerivative;

    /**
     * Generate every profile configured in `config('media-assets.generate_on_upload')`.
     *
     * @return array<int, AssetDerivative>
     */
    public function generateAllForUpload(Asset $asset): array;
}
