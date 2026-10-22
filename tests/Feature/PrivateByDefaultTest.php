<?php

declare(strict_types=1);

namespace ForgeStack\MediaAssets\Tests\Feature;

use ForgeStack\MediaAssets\Models\Asset;
use ForgeStack\MediaAssets\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

/**
 * M-01: sin derivado no hay URL pública. El original de impresión nunca debe
 * salir por urlFor() ni temporaryUrlFor() salvo que la aplicación lo active.
 */
class PrivateByDefaultTest extends TestCase
{
    use RefreshDatabase;

    private function assetWithoutDerivative(): Asset
    {
        Storage::fake('public');
        Storage::disk('public')->put('assets/test/original.jpg', 'source-bytes');

        return Asset::factory()->create([
            'disk' => 'public',
            'storage_path' => 'assets/test/original.jpg',
        ]);
    }

    private function addDerivative(Asset $asset, string $profile): void
    {
        $asset->derivatives()->create([
            'profile' => $profile,
            'disk' => 'public',
            'storage_path' => "assets/test/{$profile}.jpg",
            'width' => 320,
            'height' => 213,
            'size_bytes' => 11,
            'format' => 'jpg',
        ]);
    }

    public function test_without_a_derivative_the_public_url_is_null_by_default(): void
    {
        $asset = $this->assetWithoutDerivative();

        $this->assertNull($asset->urlFor('thumbnail'));
    }

    public function test_without_a_derivative_the_signed_url_is_null_by_default(): void
    {
        $asset = $this->assetWithoutDerivative();

        $this->assertNull($asset->temporaryUrlFor('thumbnail'));
    }

    public function test_has_derivative_is_false_until_the_profile_exists(): void
    {
        $asset = $this->assetWithoutDerivative();

        $this->assertFalse($asset->hasDerivative('thumbnail'));

        $this->addDerivative($asset, 'thumbnail');

        $this->assertTrue($asset->fresh()->hasDerivative('thumbnail'));
    }

    public function test_with_a_derivative_the_public_url_is_the_derivative(): void
    {
        $asset = $this->assetWithoutDerivative();
        $this->addDerivative($asset, 'thumbnail');

        $this->assertSame(
            $asset->fresh()->derivatives()->where('profile', 'thumbnail')->firstOrFail()->url(),
            $asset->fresh()->urlFor('thumbnail'),
        );
    }

    public function test_the_application_can_opt_in_to_the_original_fallback(): void
    {
        config(['media-assets.fallback_to_original' => true]);

        $asset = $this->assetWithoutDerivative();

        $this->assertSame($asset->originalUrl(), $asset->urlFor('thumbnail'));
    }
}
