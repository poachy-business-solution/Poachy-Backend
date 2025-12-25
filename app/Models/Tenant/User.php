<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'is_active',
        'last_login_at',
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
}
