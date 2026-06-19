<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusinessDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'central';
    protected $table = 'business_details';

    public const BUSINESS_STATUS = ['active', 'inactive', 'suspended', 'pending'];

    protected $fillable = [
        'tenant_id',
        'business_name',
        'business_description',
        'business_logo',
        'business_banner',
        'business_type_id',
        'business_category_id',
        'business_email',
        'business_phone',
        'contact_person',
        'address',
        'city',
        'county',
        'operating_hours',
        'delivery_info',
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
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'onboarded_at' => 'datetime',
    ];

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
}
