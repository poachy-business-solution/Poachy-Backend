<?php

namespace App\Http\Resources\Tenant\Concerns;

trait ResolvesTenantPublicAssetUrls
{
    protected function tenantPublicAssetUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        return tenant_asset($this->normalizeTenantPublicAssetPath($path));
    }

    protected function tenantPublicAssetUrls(?array $paths): array
    {
        if (empty($paths)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($path) => $this->tenantPublicAssetUrl(is_string($path) ? $path : null),
            $paths
        )));
    }

    private function normalizeTenantPublicAssetPath(string $path): string
    {
        $path = ltrim($path, '/');

        return str_starts_with($path, 'storage/')
            ? substr($path, strlen('storage/'))
            : $path;
    }
}
