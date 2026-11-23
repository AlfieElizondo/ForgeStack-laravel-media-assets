<?php

declare(strict_types=1);

namespace ForgeStack\MediaAssets\Tests\Unit;

use ForgeStack\MediaAssets\Drivers\Derivatives\InterventionImageProcessor;
use ForgeStack\MediaAssets\Tests\TestCase;
use ForgeStack\MediaAssets\ValueObjects\ImageProfile;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * M-03: los derivados son públicos. No deben conservar metadatos que identifiquen
 * cámara, autor o ubicación. El perfil ICC sí se conserva y la orientación EXIF
 * se aplica a los píxeles antes de quitarla.
 *
 * Fixture 64×48 con Make/Model/LensModel, Artist/Copyright/SerialNumber, GPS,
 * perfil ICC Display P3 y Orientation=6 (girar 90° → el derivado sale vertical).
 */
class DerivativeMetadataStripTest extends TestCase
{
    private const FIXTURE = __DIR__.'/../Fixtures/exif-camera-author-gps-p3.jpg';

    private const IDENTIFYING_TAGS = [
        'Make', 'Model', 'LensModel', 'Artist', 'Copyright', 'SerialNumber',
        'GPSLatitude', 'GPSLongitude', 'Orientation',
    ];

    /** @return array<string, array{class-string}> */
    public static function drivers(): array
    {
        $drivers = [];

        if (extension_loaded('imagick')) {
            $drivers['imagick'] = [ImagickDriver::class];
        }

        if (extension_loaded('gd')) {
            $drivers['gd'] = [GdDriver::class];
        }

        return $drivers;
    }

    public function test_the_fixture_really_carries_identifying_metadata(): void
    {
        $tags = $this->exiftool(self::FIXTURE);

        foreach (self::IDENTIFYING_TAGS as $tag) {
            $this->assertArrayHasKey($tag, $tags, "La fixture debería traer {$tag}.");
        }
    }

    #[DataProvider('drivers')]
    public function test_jpeg_derivatives_carry_no_identifying_metadata(string $driver): void
    {
        $tags = $this->exiftool($this->derivative($driver, 'jpg'));

        foreach (self::IDENTIFYING_TAGS as $tag) {
            $this->assertArrayNotHasKey($tag, $tags, "El derivado JPEG conserva {$tag}.");
        }
    }

    #[DataProvider('drivers')]
    public function test_webp_derivatives_carry_no_identifying_metadata(string $driver): void
    {
        $tags = $this->exiftool($this->derivative($driver, 'webp'));

        foreach (self::IDENTIFYING_TAGS as $tag) {
            $this->assertArrayNotHasKey($tag, $tags, "El derivado WebP conserva {$tag}.");
        }
    }

    #[DataProvider('drivers')]
    public function test_derivatives_apply_the_exif_orientation_to_the_pixels(string $driver): void
    {
        [$width, $height] = getimagesize($this->derivative($driver, 'jpg'));

        $this->assertSame([48, 64], [$width, $height]);
    }

    public function test_imagick_derivatives_keep_the_icc_color_profile(): void
    {
        if (! extension_loaded('imagick')) {
            $this->markTestSkipped('imagick no instalado');
        }

        $tags = $this->exiftool($this->derivative(ImagickDriver::class, 'jpg'));

        $this->assertSame('Display P3', $tags['ProfileDescription'] ?? null);
    }

    private function derivative(string $driver, string $format): string
    {
        $processor = new InterventionImageProcessor(new ImageManager($driver));
        $profile = ImageProfile::fromConfig('m03_test', [
            'max_width' => 2000, 'max_height' => 2000, 'quality' => 85, 'format' => $format,
        ]);

        $path = tempnam(sys_get_temp_dir(), 'm03_').'.'.$format;
        file_put_contents($path, $processor->process(self::FIXTURE, $profile)->contents);
        $this->beforeApplicationDestroyed(fn () => @unlink($path));

        return $path;
    }

    /** @return array<string, mixed> */
    private function exiftool(string $path): array
    {
        exec('command -v exiftool', $found, $status);

        if ($status !== 0) {
            $this->markTestSkipped('exiftool no instalado');
        }

        exec('exiftool -j -s -G0 '.escapeshellarg($path), $output);
        $tags = json_decode(implode('', $output), true)[0] ?? [];

        // Sin grupo de archivo/compuesto: solo metadatos que viajan dentro de la imagen.
        $embedded = [];
        foreach ($tags as $key => $value) {
            [$group, $name] = array_pad(explode(':', $key, 2), 2, null);
            if ($name !== null && ! in_array($group, ['File', 'Composite', 'SourceFile', 'ExifTool'], true)) {
                $embedded[$name] = $value;
            }
        }

        return $embedded;
    }
}
