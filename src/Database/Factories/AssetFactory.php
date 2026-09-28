<?php

declare(strict_types=1);

namespace ForgeStack\MediaAssets\Database\Factories;

use ForgeStack\MediaAssets\Enums\AssetStatus;
use ForgeStack\MediaAssets\Models\Asset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    protected $model = Asset::class;

    public function definition(): array
    {
        return [
            'type' => 'image',
            'disk' => 'public',
            'storage_path' => 'assets/'.$this->faker->uuid().'/original.jpg',
            'original_filename' => $this->faker->word().'.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => $this->faker->numberBetween(50_000, 5_000_000),
            'status' => AssetStatus::Uploading,
        ];
    }

    public function ready(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AssetStatus::Ready,
            'width' => 4000,
            'height' => 3000,
        ]);
    }
}
