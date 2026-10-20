<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class BusinessDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'central';

    protected $table = 'business_details';

    public const BUSINESS_STATUS = ['active', 'inactive', 'suspended', 'pending'];

    protected $fillable = [
        'tenant_id',
        'business_name',
        'slug',
        'business_description',
        'business_logo',
        'business_banner',
        'business_type_id',
        'business_category_id',
        'business_email',
        'business_phone',
        'payout_phone',
        'payouts_held_at',
        'payouts_hold_reason',
        'whatsapp_number',
        'contact_person',
        'address',
        'city',
        'county',
        'latitude',
        'longitude',
        'operating_hours',
        'delivery_info',
        'return_window_days',
        'charge_vat',
        'rating',
        'rating_count',
        'is_verified',
        'verified_at',
        'settings',
        'social_media',
        'status',
        'onboarded_at',
    ];

    protected $casts = [
        'business_type_id' => 'integer',
        'business_category_id' => 'integer',
        'operating_hours' => 'array',
        'delivery_info' => 'array',
        'settings' => 'array',
        'social_media' => 'array',
        'rating' => 'decimal:2',
        'rating_count' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'return_window_days' => 'integer',
        'charge_vat' => 'boolean',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'onboarded_at' => 'datetime',
    ];

    /** Return windows a shop can offer (days). 0 means no returns. */
    public const RETURN_WINDOWS = [0, 3, 7];

    protected static function booted(): void
    {
        // Every shop has a public slug from the day it is created (sign-up, admin onboarding, imports).
        static::saving(function (BusinessDetail $detail) {
            if (blank($detail->slug)) {
                $detail->slug = static::uniqueSlug((string) $detail->business_name, $detail->id);
            }
        });
    }

    /** A URL-safe slug from a name that no other shop uses. */
    public static function uniqueSlug(string $name, ?int $exceptId = null): string
    {
        $base = Str::limit(Str::slug($name) ?: 'shop', 50, '');
        $slug = $base;

        for ($n = 2; static::on('central')->withTrashed()->where('slug', $slug)->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }

    /** Listed on the marketplace and reachable on its public page: verified and active. */
    public function isListed(): bool
    {
        return $this->is_verified && $this->status === 'active';
    }

    public function scopeListed($query)
    {
        return $query->where('is_verified', true)->where('status', 'active');
    }

    /** https://wa.me link for the WhatsApp number, or null when the shop has none. */
    public function getWhatsappLinkAttribute(): ?string
    {
        return $this->whatsapp_number ? 'https://wa.me/'.$this->whatsapp_number : null;
    }

    /** Human label for the return window, shown on product pages and at checkout. */
    public function getReturnPolicyLabelAttribute(): string
    {
        return match ((int) $this->return_window_days) {
            0 => 'No returns',
            default => "Returns within {$this->return_window_days} days",
        };
    }

    /** The shop's page on the web (poachy.com/s/{slug}). */
    public function getPublicUrlAttribute(): ?string
    {
        return $this->slug ? rtrim((string) config('app.frontend_url', config('app.url')), '/').'/s/'.$this->slug : null;
    }

    // Relationships

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function businessType()
    {
        return $this->belongsTo(BusinessType::class);
    }

    public function businessCategory()
    {
        return $this->belongsTo(BusinessCategory::class);
    }

    // Status Methods

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isVerified(): bool
    {
        return $this->is_verified === true;
    }

    public function activate(): bool
    {
        return $this->update([
            'status' => 'active',
            'onboarded_at' => now(),
        ]);
    }

    public function suspend(): bool
    {
        return $this->update(['status' => 'suspended']);
    }

    public function deactivate(): bool
    {
        return $this->update(['status' => 'inactive']);
    }

    public function verify(): bool
    {
        return $this->update([
            'is_verified' => true,
            'verified_at' => now(),
        ]);
    }

    public function unverify(): bool
    {
        return $this->update([
            'is_verified' => false,
            'verified_at' => null,
        ]);
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function scopeByType($query, int $typeId)
    {
        return $query->where('business_type_id', $typeId);
    }

    public function scopeByCategory($query, int $categoryId)
    {
        return $query->where('business_category_id', $categoryId);
    }

    public function scopeInLocation($query, ?string $city = null, ?string $county = null)
    {
        if ($city) {
            $query->where('city', $city);
        }
        if ($county) {
            $query->where('county', $county);
        }

        return $query;
    }

    /**
     * Whether this shop adds VAT on top of its prices (item 48: off unless the shop switches it
     * on). Used by the till and by marketplace checkout.
     */
    public static function chargesVat(?string $tenantId): bool
    {
        if ($tenantId === null || $tenantId === '') {
            return false;
        }

        // Not cached: queue workers live long and the owner can switch it at any time.
        return (bool) static::on('central')->where('tenant_id', $tenantId)->value('charge_vat');
    }
}
