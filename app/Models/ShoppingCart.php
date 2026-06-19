<?php

namespace App\Models;

use App\Enums\Central\CartStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShoppingCart extends Model
{
    protected $connection = 'central';

    protected $table = 'shopping_carts';

    protected $fillable = [
        'customer_id',
        'session_id',
        'status',
        'abandoned_at',
        'converted_at',
        'converted_order_id',
        'device_type',
        'browser',
        'platform',
        'user_agent',
        'ip_address',
        'recovery_email_sent',
        'recovery_email_sent_at',
        'recovery_sms_sent',
        'recovery_sms_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'status'                => CartStatus::class,
            'abandoned_at'          => 'datetime',
            'converted_at'          => 'datetime',
            'recovery_email_sent'   => 'boolean',
            'recovery_email_sent_at' => 'datetime',
            'recovery_sms_sent'     => 'boolean',
            'recovery_sms_sent_at'  => 'datetime',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function customer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCustomer::class, 'customer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShoppingCartItem::class, 'cart_id');
    }

    public function convertedOrder(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrder::class, 'converted_order_id');
    }

    public function checkoutSessions(): HasMany
    {
        return $this->hasMany(CheckoutSession::class, 'cart_id');
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', CartStatus::Active);
    }

    public function scopeAbandoned(Builder $query): Builder
    {
        return $query->where('status', CartStatus::Abandoned);
    }

    public function scopeByCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeBySession(Builder $query, string $sessionId): Builder
    {
        return $query->where('session_id', $sessionId);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    public function isActive(): bool
    {
        return $this->status === CartStatus::Active;
    }

    public function isEmpty(): bool
    {
        return $this->items()->count() === 0;
    }

    public function markAsAbandoned(): bool
    {
        return $this->update([
            'status'       => CartStatus::Abandoned,
            'abandoned_at' => now(),
        ]);
    }

    public function markAsConverted(?int $orderId = null): bool
    {
        return $this->update([
            'status'             => CartStatus::Converted,
            'converted_at'       => now(),
            'converted_order_id' => $orderId,
        ]);
    }

    public function markAsExpired(): bool
    {
        return $this->update([
            'status' => CartStatus::Expired,
        ]);
    }

    public function getSubtotal(): float
    {
        return $this->items->sum(fn (ShoppingCartItem $item) => $item->getLineTotal());
    }

    public function getItemCount(): int
    {
        return $this->items->count();
    }
}
