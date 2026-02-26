<?php

namespace App\Models;

use App\Enums\Central\DeliveryMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantDeliveryZone extends Model
{
    protected $connection = 'central';

    protected $table = 'tenant_delivery_zones';

    protected $fillable = [
        'tenant_id',
        'tenant_zone_id',
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
        'last_synced_at',
        'sync_status',
    ];

    protected function casts(): array
    {
        return [
            'cities'                   => 'array',
            'counties'                 => 'array',
            'postal_codes'             => 'array',
            'supported_methods'        => 'array',
            'latitude'                 => 'decimal:8',
            'longitude'                => 'decimal:8',
            'standard_fee'             => 'decimal:2',
            'express_fee'              => 'decimal:2',
            'scheduled_fee'            => 'decimal:2',
            'free_delivery_threshold'  => 'decimal:2',
            'radius_km'                => 'integer',
            'priority'                 => 'integer',
            'is_active'                => 'boolean',
            'last_synced_at'           => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (TenantDeliveryZone $zone) {
            // Normalize to lowercase for case-insensitive matching
            if ($zone->cities) {
                $zone->cities = array_map('strtolower', array_map('trim', $zone->cities));
            }

            if ($zone->counties) {
                $zone->counties = array_map('strtolower', array_map('trim', $zone->counties));
            }

            // Ensure supported_methods always has a value
            if (empty($zone->supported_methods)) {
                $zone->supported_methods = ['standard'];
            }
        });
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    // =========================================================================
    // Zone Matching
    // =========================================================================

    /**
     * Check if this zone covers the given customer address.
     * Falls back gracefully when address data is incomplete.
     */
    public function matchesAddress(CustomerAddress $address): bool
    {
        return match ($this->zone_type) {
            'city'        => $this->matchesCity($address->city),
            'county'      => $this->matchesCounty($address->county),
            'postal_code' => $this->matchesPostalCode($address->postal_code),
            'radius'      => $this->matchesRadius($address->latitude, $address->longitude, $address),
            default       => false,
        };
    }

    private function matchesCity(?string $city): bool
    {
        if (! $city || ! $this->cities) {
            return false;
        }

        // Cities are already normalized to lowercase on write
        return in_array(strtolower(trim($city)), $this->cities);
    }

    private function matchesCounty(?string $county): bool
    {
        if (! $county || ! $this->counties) {
            return false;
        }

        // Counties are already normalized to lowercase on write
        return in_array(strtolower(trim($county)), $this->counties);
    }

    private function matchesPostalCode(?string $postalCode): bool
    {
        if (! $postalCode || ! $this->postal_codes) {
            return false;
        }

        return in_array(trim($postalCode), $this->postal_codes);
    }

    private function matchesRadius(?string $lat, ?string $lng, CustomerAddress $address): bool
    {
        // If lat/lng missing on address, fall back to city/county matching
        if (! $lat || ! $lng) {
            return $this->matchesCity($address->city) || $this->matchesCounty($address->county);
        }

        if (! $this->latitude || ! $this->longitude || ! $this->radius_km) {
            return false;
        }

        $distance = $this->haversineDistance((float) $lat, (float) $lng);

        return $distance <= $this->radius_km;
    }

    /**
     * Haversine formula — returns distance in kilometres.
     */
    private function haversineDistance(float $lat, float $lng): float
    {
        $earthRadius = 6371;

        $dLat = deg2rad($lat - (float) $this->latitude);
        $dLng = deg2rad($lng - (float) $this->longitude);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad((float) $this->latitude)) * cos(deg2rad($lat))
            * sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    // =========================================================================
    // Fee Helpers
    // =========================================================================

    /**
     * Whether this zone supports the given delivery method.
     */
    public function supportsMethod(DeliveryMethod $method): bool
    {
        return in_array($method->value, $this->supported_methods ?? []);
    }

    /**
     * Get the delivery fee for the given method.
     *
     * @throws \RuntimeException if method is not supported
     */
    public function getFeeForMethod(DeliveryMethod $method): float
    {
        if (! $this->supportsMethod($method)) {
            throw new \RuntimeException(
                "The '{$method->label()}' delivery method is not available for your area."
            );
        }

        return match ($method) {
            DeliveryMethod::Standard  => (float) ($this->standard_fee ?? 0),
            DeliveryMethod::Express   => (float) ($this->express_fee ?? 0),
            DeliveryMethod::Scheduled => (float) ($this->scheduled_fee ?? 0),
        };
    }

    /**
     * Get the estimated delivery time string for the given method.
     */
    public function getEstimatedTimeForMethod(DeliveryMethod $method): ?string
    {
        return match ($method) {
            DeliveryMethod::Standard  => $this->standard_delivery_time,
            DeliveryMethod::Express   => $this->express_delivery_time,
            DeliveryMethod::Scheduled => $this->scheduled_delivery_time,
        };
    }
}
