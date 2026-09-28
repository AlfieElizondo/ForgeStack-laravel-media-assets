<?php

declare(strict_types=1);

namespace ForgeStack\MediaAssets\Drivers\Metadata;

use DateTimeImmutable;
use ForgeStack\MediaAssets\Contracts\MetadataExtractor;
use ForgeStack\MediaAssets\DTOs\AssetMetadata;
use ForgeStack\MediaAssets\DTOs\CameraInfo;
use ForgeStack\MediaAssets\DTOs\ExposureInfo;
use ForgeStack\MediaAssets\DTOs\GpsCoordinates;
use ForgeStack\MediaAssets\ValueObjects\ImageDimensions;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Metadata driver based on the `exiftool` binary (Perl).
 *
 * Covers EXIF + ICC + GPS + XMP + maker notes. Requires installation on
 * the host (`brew install exiftool` on macOS, `apt install
 * libimage-exiftool-perl` on Debian/Ubuntu, `dnf install
 * perl-Image-ExifTool` on RHEL/AlmaLinux).
 *
 * Defensive: if the binary is unavailable or fails, falls back to
 * getimagesize() for dimensions and returns null for the rest. Never
 * throws — a missing binary or unreadable file must never fail an
 * upload.
 */
final class ExifToolMetadataExtractor implements MetadataExtractor
{
    private const SUPPORTED_MIMES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/tiff',
        'image/webp',
        'image/heic',
        'image/heif',
    ];

    /** EXIF tags requested from exiftool. -j for JSON, -n for numeric values. */
    private const EXIF_TAGS = [
        '-ImageWidth', '-ImageHeight',
        '-Make', '-Model', '-LensModel', '-Lens',
        '-ISO', '-FNumber', '-FocalLength', '-ExposureTime', '-ShutterSpeedValue',
        '-GPSLatitude', '-GPSLongitude', '-GPSAltitude',
        '-DateTimeOriginal', '-CreateDate',
        '-ICC_Profile:ProfileDescription', '-ColorSpace',
    ];

    public function __construct(
        private readonly string $binaryPath = 'exiftool',
        private readonly int $timeoutSeconds = 10,
    ) {}

    public function extract(string $absolutePath): AssetMetadata
    {
        $rawPayload = $this->runExifTool($absolutePath);

        $dimensions = $this->extractDimensions($rawPayload, $absolutePath);

        if (empty($rawPayload)) {
            return new AssetMetadata(
                dimensions: $dimensions,
                raw: ['extractor' => $this->name(), 'available' => false],
            );
        }

        return new AssetMetadata(
            dimensions: $dimensions,
            camera: $this->extractCamera($rawPayload),
            exposure: $this->extractExposure($rawPayload),
            gps: $this->extractGps($rawPayload),
            iccProfile: $this->str($rawPayload, 'ProfileDescription'),
            colorSpace: $this->str($rawPayload, 'ColorSpace'),
            capturedAt: $this->extractCapturedAt($rawPayload),
            raw: ['extractor' => $this->name()] + $rawPayload,
        );
    }

    public function supports(string $mimeType): bool
    {
        return in_array(strtolower($mimeType), self::SUPPORTED_MIMES, true);
    }

    public function name(): string
    {
        return 'exiftool';
    }

    // ── Internals ────────────────────────────────────────────────────────

    /**
     * Runs exiftool and returns the first JSON record. Returns an empty
     * array for any failure scenario (missing binary, invalid file,
     * timeout, malformed JSON). Never throws.
     */
    private function runExifTool(string $absolutePath): array
    {
        $command = array_merge(
            [$this->binaryPath, '-j', '-n'],
            self::EXIF_TAGS,
            [$absolutePath],
        );

        try {
            $process = new Process($command);
            $process->setTimeout($this->timeoutSeconds);
            $process->run();

            if (! $process->isSuccessful()) {
                Log::info('ExifToolMetadataExtractor: process unsuccessful', [
                    'path' => $absolutePath,
                    'stderr' => $process->getErrorOutput(),
                ]);

                return [];
            }

            $output = trim($process->getOutput());
            $decoded = json_decode($output, true);

            if (! is_array($decoded) || empty($decoded[0])) {
                return [];
            }

            return $decoded[0];
        } catch (ProcessFailedException|ProcessTimedOutException $e) {
            Log::warning('ExifToolMetadataExtractor: process exception', [
                'path' => $absolutePath,
                'message' => $e->getMessage(),
            ]);

            return [];
        } catch (Throwable $e) {
            // A missing binary lands here (Process throws RuntimeException).
            Log::info('ExifToolMetadataExtractor: unavailable, falling back', [
                'path' => $absolutePath,
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function extractDimensions(array $payload, string $absolutePath): ?ImageDimensions
    {
        $w = $this->int($payload, 'ImageWidth');
        $h = $this->int($payload, 'ImageHeight');

        if ($w !== null && $h !== null) {
            return new ImageDimensions($w, $h);
        }

        $info = @getimagesize($absolutePath);
        if (is_array($info) && isset($info[0], $info[1])) {
            return new ImageDimensions((int) $info[0], (int) $info[1]);
        }

        return null;
    }

    private function extractCamera(array $payload): ?CameraInfo
    {
        $info = new CameraInfo(
            make: $this->str($payload, 'Make'),
            model: $this->str($payload, 'Model'),
            lens: $this->str($payload, 'LensModel') ?? $this->str($payload, 'Lens'),
        );

        return $info->isEmpty() ? null : $info;
    }

    private function extractExposure(array $payload): ?ExposureInfo
    {
        $info = new ExposureInfo(
            iso: $this->int($payload, 'ISO'),
            aperture: $this->float($payload, 'FNumber'),
            focalLength: $this->float($payload, 'FocalLength'),
            shutterSpeed: $this->extractShutterSpeed($payload),
        );

        return $info->isEmpty() ? null : $info;
    }

    /**
     * ExposureTime comes as a decimal (0.004) or a fraction ("1/250").
     * We prefer the fraction form when it's under a second because
     * that's what a photographer expects to see.
     */
    private function extractShutterSpeed(array $payload): ?string
    {
        $exposureTime = $payload['ExposureTime'] ?? null;

        if ($exposureTime === null) {
            return null;
        }

        if (is_numeric($exposureTime) && $exposureTime > 0 && $exposureTime < 1) {
            $denominator = (int) round(1 / $exposureTime);

            return "1/{$denominator}";
        }

        return (string) $exposureTime;
    }

    private function extractGps(array $payload): ?GpsCoordinates
    {
        $lat = $this->float($payload, 'GPSLatitude');
        $lng = $this->float($payload, 'GPSLongitude');

        if ($lat === null || $lng === null) {
            return null;
        }

        return new GpsCoordinates(
            latitude: $lat,
            longitude: $lng,
            altitudeMeters: $this->float($payload, 'GPSAltitude'),
        );
    }

    private function extractCapturedAt(array $payload): ?DateTimeImmutable
    {
        $raw = $payload['DateTimeOriginal'] ?? $payload['CreateDate'] ?? null;

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        // EXIF format: 'YYYY:MM:DD HH:MM:SS'
        try {
            return DateTimeImmutable::createFromFormat('Y:m:d H:i:s', $raw) ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    // ── Coercion helpers (exiftool returns mixed scalar types) ───────────

    private function str(array $payload, string $key): ?string
    {
        $v = $payload[$key] ?? null;

        return is_string($v) && $v !== '' ? $v : null;
    }

    private function int(array $payload, string $key): ?int
    {
        $v = $payload[$key] ?? null;

        return is_numeric($v) ? (int) $v : null;
    }

    private function float(array $payload, string $key): ?float
    {
        $v = $payload[$key] ?? null;

        return is_numeric($v) ? (float) $v : null;
    }
}
