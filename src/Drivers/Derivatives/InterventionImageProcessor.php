<?php

declare(strict_types=1);

namespace ForgeStack\MediaAssets\Drivers\Derivatives;

use ForgeStack\MediaAssets\ValueObjects\ImageDimensions;
use ForgeStack\MediaAssets\ValueObjects\ImageProfile;
use ForgeStack\MediaAssets\ValueObjects\ProcessedImage;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

/**
 * Implementation based on Intervention Image v4. The active driver
 * (Imagick vs GD) is decided in config/media-assets.php -> 'driver'.
 */
final class InterventionImageProcessor implements ImageProcessor
{
    public function __construct(
        private readonly ImageManager $manager,
    ) {}

    public function process(string $sourcePath, ImageProfile $profile): ProcessedImage
    {
        $image = $this->manager->decodePath($sourcePath);

        $image->scaleDown(
            width: $profile->maxWidth,
            height: $profile->maxHeight,
        );

        $encoder = match ($profile->format) {
            'jpg', 'jpeg' => new JpegEncoder(quality: $profile->quality, progressive: true),
            'webp' => new WebpEncoder(quality: $profile->quality),
            'png' => new PngEncoder,
        };

        $contents = (string) $image->encode($encoder);

        return new ProcessedImage(
            contents: $contents,
            dimensions: new ImageDimensions($image->width(), $image->height()),
            format: $profile->format,
            sizeBytes: strlen($contents),
        );
    }

    public function dimensions(string $sourcePath): ImageDimensions
    {
        $image = $this->manager->decodePath($sourcePath);

        return new ImageDimensions($image->width(), $image->height());
    }
}
