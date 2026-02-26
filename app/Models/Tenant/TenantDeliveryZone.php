<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantDeliveryZone extends Model
{
    use HasFactory;

    protected $table = 'tenant_delivery_zones';

    protected $fillable = [
        'zone_name',
        'zone_type',
        'cities',
        'counties',
        'postal_codes',
        'latitude',
        'longitude',
        'radius_km',
        'standard_fee',
        'express_fee',
        'scheduled_fee',
        'free_delivery_threshold',
        'standard_delivery_time',
        'express_delivery_time',
        'scheduled_delivery_time',
        'supported_methods',
        'priority',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cities'                  => 'array',
            'counties'                => 'array',
            'postal_codes'            => 'array',
            'supported_methods'       => 'array',
            'latitude'                => 'decimal:8',
            'longitude'               => 'decimal:8',
            'standard_fee'            => 'decimal:2',
            'express_fee'             => 'decimal:2',
            'scheduled_fee'           => 'decimal:2',
            'free_delivery_threshold' => 'decimal:2',
            'radius_km'               => 'integer',
            'priority'                => 'integer',
            'is_active'               => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (TenantDeliveryZone $zone) {
            if ($zone->cities) {
                $zone->cities = array_map('strtolower', array_map('trim', $zone->cities));
            }

            if ($zone->counties) {
                $zone->counties = array_map('strtolower', array_map('trim', $zone->counties));
            }

            if (empty($zone->supported_methods)) {
                $zone->supported_methods = ['standard'];
            }
        });
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    public function scopeActive($query): void
    {
        $query->where('is_active', true);
    }

    public function scopeByPriority($query): void
    {
        $query->orderBy('priority')->orderBy('zone_name');
    }
}
