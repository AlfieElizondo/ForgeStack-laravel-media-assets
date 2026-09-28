<?php

declare(strict_types=1);

namespace ForgeStack\MediaAssets\Contracts;

use ForgeStack\MediaAssets\DTOs\AssetMetadata;

/**
 * Extracts metadata (EXIF, ICC, GPS, dimensions, capture date) from an
 * asset's original file. V1 implements ExifToolMetadataExtractor (the
 * `exiftool` binary).
 *
 * Defensive contract: no implementation should throw for recoverable
 * problems (no EXIF present, exotic format, etc.) — return an
 * AssetMetadata with whatever fields were available and null for the
 * rest. Only throw for unrecoverable errors (file missing, permissions).
 */
interface MetadataExtractor
{
    /**
     * @param string $absolutePath Absolute path on the local filesystem.
     */
    public function extract(string $absolutePath): AssetMetadata;

    /**
     * Can this driver extract useful metadata from this mime type?
     * Callers use this to decide whether it's worth calling extract().
     */
    public function supports(string $mimeType): bool;

    /**
     * Stable driver identifier. Persisted on `metadata.extractor` for
     * traceability.
     */
    public function name(): string;
}
