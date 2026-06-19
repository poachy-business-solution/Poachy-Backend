<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckoutSession extends Model
{
    protected $connection = 'central';

    protected $table = 'checkout_sessions';

    protected $fillable = [
        'cart_id',
        'customer_id',
        'current_step',
        'step_cart_viewed',
        'step_cart_viewed_at',
        'step_shipping_viewed',
        'step_shipping_viewed_at',
        'step_shipping_completed',
        'step_shipping_completed_at',
        'step_payment_viewed',
        'step_payment_viewed_at',
        'step_payment_attempted',
        'step_payment_attempted_at',
        'step_review_viewed',
        'step_review_viewed_at',
        'is_completed',
        'completed_at',
        'completed_order_id',
        'is_abandoned',
        'abandoned_at',
        'abandoned_at_step',
        'device_type',
        'browser',
        'abandonment_reasons',
    ];

    protected function casts(): array
    {
        return [
            'step_cart_viewed'           => 'boolean',
            'step_cart_viewed_at'        => 'datetime',
            'step_shipping_viewed'       => 'boolean',
            'step_shipping_viewed_at'    => 'datetime',
            'step_shipping_completed'    => 'boolean',
            'step_shipping_completed_at' => 'datetime',
            'step_payment_viewed'        => 'boolean',
            'step_payment_viewed_at'     => 'datetime',
            'step_payment_attempted'     => 'boolean',
            'step_payment_attempted_at'  => 'datetime',
            'step_review_viewed'         => 'boolean',
            'step_review_viewed_at'      => 'datetime',
            'is_completed'               => 'boolean',
            'completed_at'               => 'datetime',
            'is_abandoned'               => 'boolean',
            'abandoned_at'               => 'datetime',
            'abandonment_reasons'        => 'array',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function cart(): BelongsTo
    {
        return $this->belongsTo(ShoppingCart::class, 'cart_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCustomer::class, 'customer_id');
    }

    public function completedOrder(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrder::class, 'completed_order_id');
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    public function scopeAbandoned(Builder $query): Builder
    {
        return $query->where('is_abandoned', true);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('is_completed', true);
    }

    public function scopeByStep(Builder $query, string $step): Builder
    {
        return $query->where('current_step', $step);
    }

    public function scopeByCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    public function markStepViewed(string $step): void
    {
        $this->update([
            "step_{$step}_viewed"    => true,
            "step_{$step}_viewed_at" => now(),
            'current_step'           => $step,
        ]);
    }

    public function markStepCompleted(string $step): void
    {
        $this->update([
            "step_{$step}_completed"    => true,
            "step_{$step}_completed_at" => now(),
        ]);
    }

    public function markAsCompleted(int $orderId): void
    {
        $this->update([
            'is_completed'       => true,
            'completed_at'       => now(),
            'completed_order_id' => $orderId,
            'current_step'       => 'completed',
        ]);
    }

    public function markAsAbandoned(string $step, ?array $reasons = null): void
    {
        $this->update([
            'is_abandoned'        => true,
            'abandoned_at'        => now(),
            'abandoned_at_step'   => $step,
            'abandonment_reasons' => $reasons,
        ]);
    }
}
