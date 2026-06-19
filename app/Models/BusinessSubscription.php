<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessSubscription extends Model
{
    use HasFactory;

    protected $connection = 'central';
    protected $table = 'business_subscriptions';

    protected $fillable = [
        'tenant_id',
        'subscription_plan_id',
        'start_date',
        'end_date',
        'amount_paid',
        'currency',
        'payment_method',
        'payment_reference',
        'payment_date',
        'status', // active, cancelled, expired, trial, pending
        'auto_renew',
        'is_trial',
        'trial_ends_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'subscription_plan_id' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'amount_paid' => 'decimal:2',
        'auto_renew' => 'boolean',
        'is_trial' => 'boolean',
        'trial_ends_at' => 'date',
        'payment_date' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // Relationships

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    // Status Methods
    public function isActive(): bool
    {
        return $this->status === 'active' &&
            ($this->end_date === null || now()->lte($this->end_date));
    }

    public function isExpired(): bool
    {
        return $this->end_date && now()->gt($this->end_date);
    }

    public function isInTrial(): bool
    {
        return $this->is_trial &&
            $this->trial_ends_at &&
            now()->lte($this->trial_ends_at);
    }

    public function activate(): bool
    {
        return $this->update(['status' => 'active']);
    }

    public function cancel(?string $reason = null): bool
    {
        return $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);
    }

    public function renew(?int $days = null): bool
    {
        $days = $days ?? $this->plan->billing_cycle_days;
        $newEndDate = now()->addDays($days);

        return $this->update([
            'end_date' => $newEndDate,
            'status' => 'active',
        ]);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', now());
            });
    }

    public function scopeExpired($query)
    {
        return $query->where('end_date', '<', now());
    }

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeAutoRenew($query)
    {
        return $query->where('auto_renew', true);
    }
}
