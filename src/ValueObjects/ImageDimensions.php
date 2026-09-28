<?php

declare(strict_types=1);

namespace ForgeStack\MediaAssets\ValueObjects;

final class ImageDimensions
{
    public function __construct(
        public readonly int $width,
        public readonly int $height,
    ) {}
}
