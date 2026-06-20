<?php

namespace App\Models;

use App\Enums\Central\ReviewStatus;
use App\Models\MerchantReview;
use App\Observers\Central\TenantObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

#[ObservedBy([TenantObserver::class])]
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains, HasFactory;

    protected $connection = 'central';
    protected $table = 'tenants';

    protected $fillable = [
        'data',
        'mpesa_paybill_account',
    ];

    protected $casts = [
        'data' => 'array',
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

    public function profile()
    {
        return $this->hasOne(TenantProfile::class, 'tenant_id', 'id');
    }

    public function deliveryZones()
    {
        return $this->hasMany(TenantDeliveryZone::class, 'tenant_id', 'id');
    }

    /**
     * Tell Stancl's VirtualColumn which columns exist as real DB columns.
     * Any attribute NOT listed here gets serialised into the `data` JSON blob.
     * `mpesa_paybill_account` must be here so it is written as its own column.
     */
    public static function getCustomColumns(): array
    {
        return ['id', 'mpesa_paybill_account'];
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

    public function getMpesaAccountNumber(): ?string
    {
        return $this->mpesa_paybill_account;
    }
}
