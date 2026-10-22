# Laravel Media Assets

Upload originals, generate derivatives (thumbnail/medium/large/web), and
extract EXIF/GPS metadata — with pluggable storage, derivative, and
metadata drivers.

The package never knows who owns an asset. Your app's own model (a
`Memory`, a `Listing`, a `Submission`, ...) holds a foreign key pointing
at `assets.id` — never the other way around. That's what makes it
reusable across unrelated apps.

## Install

```bash
composer require forge-stack/laravel-media-assets
php artisan vendor:publish --tag=media-assets-config
php artisan vendor:publish --tag=media-assets-migrations
php artisan migrate
```

Requires the `exiftool` binary on the host for metadata extraction
(`brew install exiftool` / `apt install libimage-exiftool-perl` /
`dnf install perl-Image-ExifTool`), and `ext-imagick` (with `libheif`)
if you need HEIC input or ICC/TIFF support — otherwise it falls back to
`ext-gd`.

## Configuration

`config/media-assets.php`:

- `storage_driver`: `local` (dev, disk `public`) or `s3` (disk `s3` —
  works against a public **or** a fully private bucket).
- `profiles` / `generate_on_upload`: derivative sizes and which ones are
  generated automatically after upload.
- `metadata_driver`: `exiftool` (only option today).

## Usage

```php
use ForgeStack\MediaAssets\Actions\UploadAsset;

$asset = app(UploadAsset::class)->execute($request->file('photo'));
// $asset->status is Uploading; ProcessAssetJob runs async and moves it
// through Processing -> Ready, generating derivatives + metadata.

// Your own model links to it:
Memory::create([...,'asset_id' => $asset->id]);
```

Rendering, once ready:

```php
// Public bucket / local disk:
$asset->urlFor('medium');

// Private bucket — signed, expires in 15 minutes by default:
$asset->temporaryUrlFor('medium');
$asset->temporaryUrlFor('medium', expirationMinutes: 60);

// Both return null when the derivative does not exist yet.
$asset->hasDerivative('medium'); // bool
```

Since 0.2.0 a missing derivative returns `null`, not the original. The original is the full-resolution file and is not served by default (`MEDIA_ASSETS_FALLBACK_TO_ORIGINAL=false`). Apps that relied on the old fallback must either handle `null` or set the flag to `true` for internal views. Upgrade notes: this is a breaking change, so the release is 0.2.0.

Regenerate/backfill a derivative on demand:

```php
use ForgeStack\MediaAssets\Contracts\DerivativeGenerator;

app(DerivativeGenerator::class)->ensure($asset, 'web');
```

## Events

`AssetUploaded`, `AssetAvailable` (derivatives ready), `AssetProcessingFailed`,
`AssetDeleted` — listen for these in your own app to react to state
changes (update your own `Memory`/`Listing` row, notify a user, etc.).

## Testing

```bash
composer test
```

Runs against Orchestra Testbench with an in-memory SQLite database. The
derivative/metadata drivers are swapped for fakes via container binding
in tests — no real Imagick or `exiftool` install is required for the
suite to pass.
