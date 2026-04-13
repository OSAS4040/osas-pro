<?php

namespace App\Support\Media;

use Illuminate\Support\Facades\Storage;

final class TenantUploadDisk
{
    public static function name(): string
    {
        $disk = (string) config('media.tenant_upload_disk', 'public');

        return in_array($disk, ['public', 's3'], true) ? $disk : 'public';
    }

    /**
     * يستخرج المسار النسبي داخل القرص من URL سابقة الحفظ (public أو S3).
     */
    public static function pathFromStoredUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }

        if (str_contains($path, '/storage/')) {
            $pos = strpos($path, '/storage/');

            return $pos !== false ? substr($path, $pos + strlen('/storage/')) : null;
        }

        $key = ltrim($path, '/');

        return $key !== '' ? $key : null;
    }

    public static function deleteIfExists(string $url): void
    {
        $disk = self::name();
        $relative = self::pathFromStoredUrl($url);
        if ($relative === null || $relative === '') {
            return;
        }

        if (Storage::disk($disk)->exists($relative)) {
            Storage::disk($disk)->delete($relative);
        }
    }
}
