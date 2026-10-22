<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'is_active',
        'last_login_at',
        'password_chosen_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'password_chosen_at' => 'datetime',
        ];
    }

    public function managedStores()
    {
        return $this->hasMany(Store::class, 'manager_id');
    }

    public function createdStores()
    {
        return $this->hasMany(Store::class, 'created_by');
    }

    public function updatedStores()
    {
        return $this->hasMany(Store::class, 'updated_by');
    }

    public function requestedTransfers()
    {
        return $this->hasMany(StockTransfer::class, 'requested_by');
    }

    public function approvedTransfers()
    {
        return $this->hasMany(StockTransfer::class, 'approved_by');
    }

    public function sentTransfers()
    {
        return $this->hasMany(StockTransfer::class, 'sent_by');
    }

    public function receivedTransfers()
    {
        return $this->hasMany(StockTransfer::class, 'received_by');
    }

    public function refreshTokens(): HasMany
    {
        return $this->hasMany(TenantRefreshToken::class);
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(TenantDeviceToken::class);
    }

    public function createdPurchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class, 'created_by');
    }

    public function approvedPurchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class, 'approved_by');
    }

    // Helper methods

    public function isOwner(): bool
    {
        return $this->hasRole('owner');
    }

    public function isManager(): bool
    {
        return $this->hasRole('manager');
    }

    public function isCashier(): bool
    {
        return $this->hasRole('cashier');
    }

    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    public function updateLastLogin(): void
    {
        $this->update(['last_login_at' => now()]);
    }

    public function canManageStores(): bool
    {
        return $this->hasAnyRole(['owner', 'manager']);
    }

    /**
     * Branches this person is assigned to (staff only — see workableStoreIds()).
     */
    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'store_user')->withTimestamps();
    }

    /**
     * Owners, managers and admins work at every branch.
     */
    public function worksAtEveryBranch(): bool
    {
        return $this->hasAnyRole(['owner', 'manager', 'admin']);
    }

    /**
     * Active branches this person may sell, open a till or record money at:
     * every branch for owners/managers; for staff, their assigned branches,
     * or the main branch when nobody has assigned them yet (so a one-shop
     * business never has to think about assignments).
     *
     * @return array<int, int>
     */
    public function workableStoreIds(): array
    {
        $active = Store::query()->where('is_active', true);

        if ($this->worksAtEveryBranch()) {
            return $active->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $assigned = $this->stores()->where('stores.is_active', true)->orderBy('stores.id')->pluck('stores.id');

        if ($assigned->isNotEmpty()) {
            return $assigned->map(fn ($id) => (int) $id)->all();
        }

        $main = $active->orderByDesc('is_main_store')->orderBy('id')->value('id');

        return $main === null ? [] : [(int) $main];
    }

    public function canWorkAt(int $storeId): bool
    {
        return in_array($storeId, $this->workableStoreIds(), true);
    }

    /**
     * Whether this person may add, edit, remove or move the given staff member. The owner may
     * manage anyone; a manager only cashiers and themselves — never an owner or another manager,
     * so a manager can't promote or take over an owner's account.
     */
    public function canManageStaff(?User $target): bool
    {
        if ($this->hasRole('owner')) {
            return true;
        }
        if (! $this->hasRole('manager') || $target === null) {
            return false;
        }

        return $target->id === $this->id || ! $target->hasAnyRole(['owner', 'manager']);
    }
}
