<?php

declare(strict_types=1);

namespace ForgeStack\MediaAssets\DTOs;

/**
 * Exposure parameters of the capture. `shutterSpeed` is persisted as a
 * string ('1/250', '2.5') because cameras report it that way and
 * forcing it to float loses semantic precision (1/250 != 0.004 when
 * displayed to a photographer).
 */
final class ExposureInfo
{
    public function __construct(
        public readonly ?int $iso = null,
        public readonly ?float $aperture = null,
        public readonly ?float $focalLength = null,
        public readonly ?string $shutterSpeed = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'iso' => $this->iso,
            'aperture' => $this->aperture,
            'focal_length' => $this->focalLength,
            'shutter_speed' => $this->shutterSpeed,
        ], fn ($v) => $v !== null);
    }

    public function isEmpty(): bool
    {
        return $this->iso === null
            && $this->aperture === null
            && $this->focalLength === null
            && $this->shutterSpeed === null;
    }
}
