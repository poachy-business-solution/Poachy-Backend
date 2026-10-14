<?php

namespace App\Support;

/**
 * Public URLs for files on a tenant's public disk (product photos, logos) that work from
 * anywhere: the marketplace, admin and share previews all run on the central host, where
 * tenant_asset() cannot resolve the tenant. Served by GET /api/v1/public/assets/{tenant}/{path}.
 */
final class TenantPublicAssets
{
    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    public static function url(string $tenantId, ?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        return rtrim((string) config('app.url'), '/').'/api/v1/public/assets/'.$tenantId.'/'.self::normalize($path);
    }

    /**
     * @param  array<int, string|null>  $paths
     * @return array<int, string>
     */
    public static function urls(string $tenantId, array $paths): array
    {
        return array_values(array_filter(array_map(fn ($p) => self::url($tenantId, is_string($p) ? $p : null), $paths)));
    }

    /** A path is safe when it stays inside the disk and names an image. */
    public static function isSafeImagePath(string $path): bool
    {
        return preg_match('#^[A-Za-z0-9_\-][A-Za-z0-9_\-./]*$#', $path) === 1
            && ! str_contains($path, '..')
            && in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::IMAGE_EXTENSIONS, true);
    }

    public static function normalize(string $path): string
    {
        $path = ltrim($path, '/');

        return str_starts_with($path, 'storage/') ? substr($path, strlen('storage/')) : $path;
    }
}
