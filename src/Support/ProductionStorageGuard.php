<?php

declare(strict_types=1);

namespace ForgeStack\MediaAssets\Support;

use Illuminate\Contracts\Foundation\Application;
use RuntimeException;

/**
 * M-02: en producción no se guardan originales en un disco público por omisión.
 * Si falta MEDIA_ASSETS_STORAGE_DRIVER=s3, la aplicación falla al arrancar en
 * vez de guardar archivos privados en un disco público sin aviso.
 */
final class ProductionStorageGuard
{
    public static function assertSafe(Application $app): void
    {
        if (! $app->environment('production')) {
            return;
        }

        $driver = (string) config('media-assets.storage_driver');

        if ($driver === 'local' && ! (bool) config('media-assets.allow_local_in_production', false)) {
            throw new RuntimeException(
                'media-assets: storage_driver=local no está permitido en producción. '
                .'Configura MEDIA_ASSETS_STORAGE_DRIVER=s3 o, si es intencional, MEDIA_ASSETS_ALLOW_LOCAL_IN_PRODUCTION=true.'
            );
        }
    }
}
