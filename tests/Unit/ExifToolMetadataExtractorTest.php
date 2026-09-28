<?php

declare(strict_types=1);

namespace ForgeStack\MediaAssets\Tests\Unit;

use ForgeStack\MediaAssets\Drivers\Metadata\ExifToolMetadataExtractor;
use ForgeStack\MediaAssets\Tests\TestCase;

class ExifToolMetadataExtractorTest extends TestCase
{
    public function test_returns_empty_metadata_defensively_when_binary_missing(): void
    {
        $extractor = new ExifToolMetadataExtractor(binaryPath: '/no/such/exiftool-binary');

        $tmp = tempnam(sys_get_temp_dir(), 'exif-test');
        file_put_contents($tmp, 'not a real image');

        $metadata = $extractor->extract($tmp);

        $this->assertNull($metadata->camera);
        $this->assertNull($metadata->capturedAt);

        unlink($tmp);
    }

    public function test_declares_supported_mime_types_including_heic(): void
    {
        $extractor = new ExifToolMetadataExtractor;

        $this->assertTrue($extractor->supports('image/jpeg'));
        $this->assertTrue($extractor->supports('image/heic'));
        $this->assertFalse($extractor->supports('application/pdf'));
    }

    public function test_name_is_stable(): void
    {
        $this->assertSame('exiftool', (new ExifToolMetadataExtractor)->name());
    }
}
