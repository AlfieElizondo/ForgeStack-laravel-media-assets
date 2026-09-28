<?php

declare(strict_types=1);

namespace ForgeStack\MediaAssets\DTOs;

use DateTimeImmutable;
use ForgeStack\MediaAssets\ValueObjects\ImageDimensions;

/**
 * Aggregated metadata extracted from an asset's original file. Every
 * field is nullable — different formats (JPG vs TIFF vs PNG) and
 * different cameras expose different subsets. A driver always tries
 * its best and returns null for whatever it couldn't find.
 *
 * `raw` carries the driver's full payload for debugging; the caller
 * decides whether to persist it.
 */
final class AssetMetadata
{
    public function __construct(
        public readonly ?ImageDimensions $dimensions = null,
        public readonly ?CameraInfo $camera = null,
        public readonly ?ExposureInfo $exposure = null,
        public readonly ?GpsCoordinates $gps = null,
        public readonly ?string $iccProfile = null,
        public readonly ?string $colorSpace = null,
        public readonly ?DateTimeImmutable $capturedAt = null,
        public readonly array $raw = [],
    ) {}

    public static function empty(): self
    {
        return new self;
    }

    /**
     * Serialization for the JSON metadata column. Only includes fields
     * with a value. Dimensions and capturedAt are persisted in their
     * own dedicated columns, not here — this is the "secondary" metadata.
     */
    public function toJsonPayload(): array
    {
        $payload = [];

        if ($this->camera && ! $this->camera->isEmpty()) {
            $payload['camera'] = $this->camera->toArray();
        }

        if ($this->exposure && ! $this->exposure->isEmpty()) {
            $payload['exposure'] = $this->exposure->toArray();
        }

        if ($this->gps) {
            $payload['gps'] = $this->gps->toArray();
        }

        if ($this->iccProfile !== null) {
            $payload['icc_profile'] = $this->iccProfile;
        }

        if ($this->colorSpace !== null) {
            $payload['color_space'] = $this->colorSpace;
        }

        return $payload;
    }
}
