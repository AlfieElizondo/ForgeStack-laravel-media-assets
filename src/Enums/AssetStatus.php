<?php

declare(strict_types=1);

namespace ForgeStack\MediaAssets\Enums;

enum AssetStatus: string
{
    case Uploading = 'uploading';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';

    public function isVisible(): bool
    {
        return $this === self::Ready;
    }
}
