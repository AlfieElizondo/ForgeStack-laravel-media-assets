<?php

declare(strict_types=1);

namespace ForgeStack\MediaAssets\Support;

use ForgeStack\MediaAssets\Contracts\StorageDriver;
use ForgeStack\MediaAssets\Drivers\Storage\LocalPublicStorageDriver;
use ForgeStack\MediaAssets\Drivers\Storage\S3StorageDriver;
use InvalidArgumentException;

/**
 * Resolves a StorageDriver by disk name, not by the currently-active
 * config.
 *
 * Needed because `$asset->disk` remembers where EACH file actually
 * lives — an asset uploaded before migrating to S3 still lives on
 * 'public' even after `config('media-assets.storage_driver')` says
 * 's3'. The binding in MediaAssetsServiceProvider resolves the ACTIVE
 * driver (for new uploads); this factory resolves the correct driver
 * for reading an EXISTING asset regardless of what's active today.
 */
final class StorageDriverFactory
{
    public static function make(string $diskName): StorageDriver
    {
        return match ($diskName) {
            'public', 'local' => new LocalPublicStorageDriver($diskName),
            's3' => new S3StorageDriver,
            default => throw new InvalidArgumentException("Unknown disk for media-assets: [{$diskName}]"),
        };
    }
}
