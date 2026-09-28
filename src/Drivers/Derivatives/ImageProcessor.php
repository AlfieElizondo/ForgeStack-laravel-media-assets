<?php

declare(strict_types=1);

namespace ForgeStack\MediaAssets\Drivers\Derivatives;

use ForgeStack\MediaAssets\ValueObjects\ImageDimensions;
use ForgeStack\MediaAssets\ValueObjects\ImageProfile;
use ForgeStack\MediaAssets\ValueObjects\ProcessedImage;

/**
 * Internal contract for the Intervention-based driver. This is an
 * implementation detail (swap Imagick vs GD vs libvips), NOT part of
 * the package's public API — that's the trio in Contracts/: StorageDriver,
 * DerivativeGenerator, MetadataExtractor.
 */
interface ImageProcessor
{
    /**
     * Apply the profile to the image at the given source path and
     * return the encoded bytes + minimal metadata (dimensions, format,
     * size).
     *
     * @param string $sourcePath Absolute path on the local filesystem.
     */
    public function process(string $sourcePath, ImageProfile $profile): ProcessedImage;

    /**
     * Read the original's dimensions without processing it.
     */
    public function dimensions(string $sourcePath): ImageDimensions;
}
