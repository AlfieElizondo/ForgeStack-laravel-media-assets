<?php

declare(strict_types=1);

namespace ForgeStack\MediaAssets\ValueObjects;

use InvalidArgumentException;

final class ImageProfile
{
    public function __construct(
        public readonly string $name,
        public readonly int $maxWidth,
        public readonly ?int $maxHeight,
        public readonly int $quality,
        public readonly string $format,
    ) {
        if ($maxWidth <= 0) {
            throw new InvalidArgumentException("ImageProfile '{$name}' max_width must be > 0");
        }

        if ($quality < 1 || $quality > 100) {
            throw new InvalidArgumentException("ImageProfile '{$name}' quality must be between 1 and 100");
        }

        if (! in_array($format, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            throw new InvalidArgumentException("ImageProfile '{$name}' format '{$format}' is not supported");
        }
    }

    public static function fromConfig(string $name, array $config): self
    {
        return new self(
            name: $name,
            maxWidth: (int) ($config['max_width'] ?? 0),
            maxHeight: isset($config['max_height']) ? (int) $config['max_height'] : null,
            quality: (int) ($config['quality'] ?? 90),
            format: (string) ($config['format'] ?? 'jpg'),
        );
    }

    public static function named(string $name): self
    {
        $config = config("media-assets.profiles.{$name}");

        if (! is_array($config)) {
            throw new InvalidArgumentException("Image profile '{$name}' is not defined in config/media-assets.php");
        }

        return self::fromConfig($name, $config);
    }

    public function extension(): string
    {
        return $this->format === 'jpeg' ? 'jpg' : $this->format;
    }
}
