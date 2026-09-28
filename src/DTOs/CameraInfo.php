<?php

declare(strict_types=1);

namespace ForgeStack\MediaAssets\DTOs;

/**
 * Equipment that took the photo. All fields nullable — different
 * cameras/formats expose different subsets in EXIF.
 */
final class CameraInfo
{
    public function __construct(
        public readonly ?string $make = null,
        public readonly ?string $model = null,
        public readonly ?string $lens = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'make' => $this->make,
            'model' => $this->model,
            'lens' => $this->lens,
        ], fn ($v) => $v !== null);
    }

    public function isEmpty(): bool
    {
        return $this->make === null && $this->model === null && $this->lens === null;
    }
}
