<?php

declare(strict_types=1);

/*
 * To add a new profile (public gallery, mobile app, etc.):
 *   1. Add its entry under `profiles`.
 *   2. If it should generate automatically on upload, add it to
 *      `generate_on_upload`. Otherwise it's generated on demand via
 *      `DerivativeGenerator::ensure($asset, 'name')`.
 *
 * Switching from Imagick to libvips later is a binding change in your
 * own AppServiceProvider, not a change to this file.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Image processing driver
    |--------------------------------------------------------------------------
    |
    | Active Intervention Image driver. Falls back to 'gd' automatically
    | when ext-imagick isn't available — fine for local dev, but GD
    | cannot decode HEIC, TIFF, or preserve ICC profiles / Adobe RGB.
    | Install ext-imagick (with libheif) before the first real upload if
    | you need any of those.
    |
    */
    'driver' => env('MEDIA_ASSETS_IMAGE_DRIVER', extension_loaded('imagick') ? 'imagick' : 'gd'),

    /*
    |--------------------------------------------------------------------------
    | Image Profiles
    |--------------------------------------------------------------------------
    |
    | Each profile defines how a derivative is rendered from the
    | original. The profile name is the key consumers use in
    | `$asset->urlFor('large')`.
    |
    |   - `max_width`: max width in px. Height is scaled to preserve
    |     aspect ratio. The derivative never exceeds this dimension.
    |   - `max_height`: optional, also caps height.
    |   - `quality`: 1-100. Only applies to lossy formats (jpg, webp).
    |   - `format`: output codec (`jpg`, `webp`, `png`). Default `jpg`.
    |
    */
    'profiles' => [

        'thumbnail' => [
            'max_width' => 320,
            'quality' => 85,
            'format' => 'jpg',
        ],

        'medium' => [
            'max_width' => 768,
            'quality' => 88,
            'format' => 'jpg',
        ],

        'large' => [
            'max_width' => 1280,
            'quality' => 90,
            'format' => 'jpg',
        ],

        'web' => [
            'max_width' => 2048,
            'quality' => 92,
            'format' => 'jpg',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Generated on upload
    |--------------------------------------------------------------------------
    |
    | Profiles generated automatically once ProcessAssetJob runs after a
    | successful upload. Profiles NOT listed here are only generated on
    | demand via DerivativeGenerator::ensure(...).
    |
    */
    'generate_on_upload' => ['thumbnail', 'medium', 'large', 'web'],

    /*
    |--------------------------------------------------------------------------
    | Storage driver
    |--------------------------------------------------------------------------
    |
    | Which StorageDriver implementation to activate for NEW uploads.
    | 'local' (LocalPublicStorageDriver, disk 'public') or 's3'
    | (S3StorageDriver, disk 's3' — see AWS_* in .env). Works with a
    | public or a fully private bucket: url() only makes sense for a
    | public bucket, temporaryUrl() works either way.
    |
    */
    'storage_driver' => env('MEDIA_ASSETS_STORAGE_DRIVER', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Local storage in production (M-02)
    |--------------------------------------------------------------------------
    |
    | 'local' writes originals to a public disk. The app refuses to boot in
    | production with it unless this escape is set explicitly.
    |
    */
    'allow_local_in_production' => (bool) env('MEDIA_ASSETS_ALLOW_LOCAL_IN_PRODUCTION', false),

    /*
    |--------------------------------------------------------------------------
    | Fallback to the original (M-01)
    |--------------------------------------------------------------------------
    |
    | When false (default), urlFor() and temporaryUrlFor() return null for a
    | profile without a derivative. The original is the full-resolution file
    | and must not be served on public pages. Enable only for internal views
    | where showing the original is acceptable.
    |
    */
    'fallback_to_original' => (bool) env('MEDIA_ASSETS_FALLBACK_TO_ORIGINAL', false),

    /*
    |--------------------------------------------------------------------------
    | Derivative write disk
    |--------------------------------------------------------------------------
    |
    | Disk InterventionDerivativeGenerator writes NEW derivatives to.
    | Should match `storage_driver` above ('local' -> disk 'public', 's3'
    | -> disk 's3'). Existing assets aren't affected by changing this —
    | each one remembers its real disk in the `assets.disk` column
    | (StorageDriverFactory resolves it regardless of what's active today).
    |
    */
    'disk' => env('MEDIA_ASSETS_DISK', env('MEDIA_ASSETS_STORAGE_DRIVER', 'local') === 's3' ? 's3' : 'public'),

    /*
    |--------------------------------------------------------------------------
    | Metadata extraction driver
    |--------------------------------------------------------------------------
    |
    | - 'exiftool': requires the `exiftool` binary on the host. Full
    |   coverage: EXIF + ICC + GPS + XMP + maker notes.
    |
    */
    'metadata_driver' => env('MEDIA_ASSETS_METADATA_DRIVER', 'exiftool'),

    /*
    | Path to the exiftool binary — useful if it's outside $PATH.
    | Default 'exiftool' works on any standard install.
    */
    'exiftool_binary' => env('MEDIA_ASSETS_EXIFTOOL_BINARY', 'exiftool'),

    /*
    | Timeout (seconds) for the metadata binary. If exiftool takes
    | longer, it's aborted and logged without failing the upload.
    */
    'metadata_timeout' => (int) env('MEDIA_ASSETS_METADATA_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Derivative path template
    |--------------------------------------------------------------------------
    |
    | Pattern used to build a derivative's path. Tokens:
    |   {asset_id}   -> the Asset's numeric id
    |   {profile}    -> profile name (thumbnail/medium/large/web/...)
    |   {extension}  -> derived from the profile's `format`
    |
    */
    'derivative_path' => 'derivatives/{asset_id}/{profile}.{extension}',

];
