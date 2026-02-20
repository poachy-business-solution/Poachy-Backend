<?php

namespace App\Models;

use App\Enums\Central\ReviewStatus;
use App\Models\MerchantReview;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains, HasFactory;

    protected $connection = 'central';
    protected $table = 'tenants';

    protected $fillable = [
        'data',
        'average_rating',
        'review_count',
    ];

    protected $casts = [
        'data'           => 'array',
        'average_rating' => 'decimal:2',
        'review_count'   => 'integer',
    ];
    

    // Relationships

    public function domains()
    {
        return $this->hasMany(config('tenancy.domain_model'), 'tenant_id', 'id');
    }

    public function businessDetail()
    {
        return $this->hasOne(BusinessDetail::class, 'tenant_id', 'id');
    }

    public function subscriptions()
    {
        return $this->hasMany(BusinessSubscription::class, 'tenant_id', 'id');
    }

    public function activeSubscription()
    {
        return $this->hasOne(BusinessSubscription::class, 'tenant_id', 'id')
            ->whereIn('status', ['active', 'trial'])
            ->latest('start_date');
    }

    public function merchantReviews(): HasMany
    {
        return $this->hasMany(MerchantReview::class, 'tenant_id', 'id');
    }

    public function approvedMerchantReviews(): HasMany
    {
        return $this->hasMany(MerchantReview::class, 'tenant_id', 'id')
            ->where('status', ReviewStatus::Approved);
    }

    // Tenancy Methods

    public function getTenantKeyName(): string
    {
        return 'id';
    }

    public function getDatabaseName(): string
    {
        $prefix = config('tenancy.database.prefix', 'poachy_tenant_');
        $suffix = config('tenancy.database.suffix', '');

        return $prefix . $this->getTenantKey() . $suffix;
    }

    // Helper Methods

    public function isActive(): bool
    {
        return $this->businessDetail?->status === 'active';
    }

    public function hasActiveSubscription(): bool
    {
        return $this->activeSubscription()->exists();
    }
}
