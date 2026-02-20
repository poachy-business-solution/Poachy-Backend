<?php

namespace App\Models;

use App\Enums\Central\ReviewStatus;
use App\Observers\Central\ProductReviewObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy([ProductReviewObserver::class])]
class ProductReview extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'central';

    protected $table = 'product_reviews';

    protected $fillable = [
        'marketplace_product_id',
        'customer_id',
        'order_id',
        'rating',
        'title',
        'review_text',
        'review_images',
        'is_verified_purchase',
        'status',
        'rejection_reason',
        'moderated_at',
        'moderated_by',
        'helpful_count',
        'not_helpful_count',
        'merchant_response',
        'merchant_responded_at',
    ];

    protected function casts(): array
    {
        return [
            'rating'                => 'decimal:1',
            'review_images'         => 'array',
            'is_verified_purchase'  => 'boolean',
            'status'                => ReviewStatus::class,
            'helpful_count'         => 'integer',
            'not_helpful_count'     => 'integer',
            'moderated_at'          => 'datetime',
            'merchant_responded_at' => 'datetime',
        ];
    }

    // Relationships

    public function product(): BelongsTo
    {
        return $this->belongsTo(MarketplaceProduct::class, 'marketplace_product_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCustomer::class, 'customer_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrder::class, 'order_id');
    }

    public function votes(): MorphMany
    {
        return $this->morphMany(ReviewVote::class, 'voteable');
    }

    public function flags(): MorphMany
    {
        return $this->morphMany(ReviewFlag::class, 'flaggable');
    }

    // Scopes

    public function scopeApproved($query)
    {
        return $query->where('status', ReviewStatus::Approved);
    }

    public function scopePending($query)
    {
        return $query->where('status', ReviewStatus::Pending);
    }

    public function scopeByProduct($query, int $productId)
    {
        return $query->where('marketplace_product_id', $productId);
    }

    public function scopeByCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    // Helper Methods

    public function isOwnedByCustomer(int $customerId): bool
    {
        return $this->customer_id === $customerId;
    }

    /**
     * Whether a merchant can still post a response to this review.
     */
    public function canReceiveMerchantResponse(): bool
    {
        return $this->status === ReviewStatus::Approved
            && is_null($this->merchant_response);
    }

    /**
     * Whether the merchant's existing response is still within the 24-hour edit window.
     */
    public function isMerchantResponseEditable(): bool
    {
        return ! is_null($this->merchant_responded_at)
            && $this->merchant_responded_at->diffInHours(now()) <= 24;
    }
}
