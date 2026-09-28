<?php

declare(strict_types=1);

namespace ForgeStack\MediaAssets\Tests;

use ForgeStack\MediaAssets\MediaAssetsServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [MediaAssetsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('filesystems.disks.public', [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => '/storage',
            'visibility' => 'public',
        ]);

        $app['config']->set('media-assets.storage_driver', 'local');
        $app['config']->set('media-assets.disk', 'public');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'ForgeStack\\MediaAssets\\Database\\Factories\\'.class_basename($modelName).'Factory',
        );
    }
}
