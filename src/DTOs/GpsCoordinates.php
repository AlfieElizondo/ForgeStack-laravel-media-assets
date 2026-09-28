<?php

declare(strict_types=1);

namespace ForgeStack\MediaAssets\DTOs;

/**
 * GPS coordinates of the capture, normalized to signed decimal degrees
 * (as EXIF reports them).
 */
final class GpsCoordinates
{
    public function __construct(
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly ?float $altitudeMeters = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'lat' => $this->latitude,
            'lng' => $this->longitude,
            'altitude' => $this->altitudeMeters,
        ], fn ($v) => $v !== null);
    }
}
