<?php

namespace App\Models\Tenant;

use App\Observers\Tenant\TenantConfigurationObserver;
use App\Traits\Tenant\HasAuditLogging;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[ObservedBy([TenantConfigurationObserver::class])]
class TenantConfiguration extends Model
{
    use HasFactory, HasAuditLogging;

    protected $table = 'tenant_configurations';

    protected $fillable = [
        'config_key',
        'config_value',
        'config_type',
        'config_group',
        'description',
        'is_active',
    ];

    protected $casts = [
        'config_value' => 'json',
        'is_active' => 'boolean',
    ];

    // Cache TTL in seconds (1 hour)
    private const CACHE_TTL = 3600;

    /**
     * Override getAuditableFields from HasAuditLogging
     */
    public function getAuditableFields(): array
    {
        return [
            'config_key',
            'config_value',
            'config_type',
            'config_group',
            'is_active',
        ];
    }

    /**
     * Override getCriticalFields from HasAuditLogging
     * For TenantConfiguration, all fields are critical (full audit mode)
     */
    public function getCriticalFields(): array
    {
        return [
            'config_key',
            'config_value',
            'config_type',
            'config_group',
            'is_active',
        ];
    }

    /**
     * Get configuration value with caching
     */
    public static function get(string $key, $default = null)
    {
        $cacheKey = "tenant_config_" . tenant()->id . "_{$key}";

        return Cache::tags(['tenant', tenant()->id, 'config'])->remember(
            $cacheKey,
            self::CACHE_TTL,
            function () use ($key, $default) {
                $config = self::where('config_key', $key)
                    ->where('is_active', true)
                    ->first();

                if (!$config) {
                    return $default;
                }

                return $config->config_value;
            }
        );
    }

    /**
     * Set configuration value
     */
    public static function set(string $key, $value, string $type = 'general'): self
    {
        $config = self::updateOrCreate(
            ['config_key' => $key],
            [
                'config_value' => $value,
                'config_type' => $type,
                'is_active' => true,
            ]
        );

        // Clear cache
        self::clearCache();

        return $config;
    }

    /**
     * Check if feature is enabled
     */
    public static function isEnabled(string $key): bool
    {
        return (bool) self::get($key, false);
    }

    /**
     * Clear configuration cache
     */
    public static function clearCache(): void
    {
        Cache::tags(['tenant', tenant()->id, 'config'])->flush();
    }

    /**
     * Get all configurations by group
     */
    public static function getByGroup(string $group): array
    {
        $cacheKey = "tenant_config_group_" . tenant()->id . "_{$group}";

        return Cache::tags(['tenant', tenant()->id, 'config'])->remember(
            $cacheKey,
            self::CACHE_TTL,
            function () use ($group) {
                return self::where('config_group', $group)
                    ->where('is_active', true)
                    ->pluck('config_value', 'config_key')
                    ->toArray();
            }
        );
    }
}
