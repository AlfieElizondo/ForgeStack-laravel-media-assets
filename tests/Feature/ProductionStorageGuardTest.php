<?php

declare(strict_types=1);

namespace ForgeStack\MediaAssets\Tests\Feature;

use ForgeStack\MediaAssets\Support\ProductionStorageGuard;
use ForgeStack\MediaAssets\Tests\TestCase;
use RuntimeException;

/**
 * M-02: en producción no se guardan originales en un disco público por omisión.
 */
class ProductionStorageGuardTest extends TestCase
{
    protected function tearDown(): void
    {
        // Restaurar el entorno antes de que Testbench limpie la aplicación.
        $this->app['env'] = 'testing';

        parent::tearDown();
    }

    private function assertStorageAllowed(): void
    {
        ProductionStorageGuard::assertSafe($this->app);
    }

    public function test_local_storage_in_production_is_refused(): void
    {
        $this->app['env'] = 'production';
        config(['media-assets.storage_driver' => 'local']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/MEDIA_ASSETS_STORAGE_DRIVER/');

        $this->assertStorageAllowed();
    }

    public function test_s3_storage_in_production_is_allowed(): void
    {
        $this->app['env'] = 'production';
        config(['media-assets.storage_driver' => 's3']);

        $this->assertStorageAllowed();

        $this->assertTrue(true);
    }

    public function test_local_storage_in_production_is_allowed_with_the_explicit_escape(): void
    {
        $this->app['env'] = 'production';
        config([
            'media-assets.storage_driver' => 'local',
            'media-assets.allow_local_in_production' => true,
        ]);

        $this->assertStorageAllowed();

        $this->assertTrue(true);
    }

    public function test_local_storage_outside_production_is_allowed(): void
    {
        $this->app['env'] = 'local';
        config(['media-assets.storage_driver' => 'local']);

        $this->assertStorageAllowed();

        $this->assertTrue(true);
    }
}
