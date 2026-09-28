<?php

declare(strict_types=1);

namespace ForgeStack\MediaAssets\ValueObjects;

/**
 * Result of processing an image with an ImageProfile. Carries the
 * encoded bytes ready to persist plus the final (post-resize) dimensions.
 */
final class ProcessedImage
{
    public function __construct(
        public readonly string $contents,
        public readonly ImageDimensions $dimensions,
        public readonly string $format,
        public readonly int $sizeBytes,
    ) {}
}
