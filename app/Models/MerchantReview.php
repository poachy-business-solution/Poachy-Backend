<?php

namespace App\Models;

use App\Enums\Central\ReviewStatus;
use App\Observers\Central\MerchantReviewObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy([MerchantReviewObserver::class])]
class MerchantReview extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'central';

    protected $table = 'merchant_reviews';

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'order_id',
        'overall_rating',
        'product_quality_rating',
        'delivery_rating',
        'service_rating',
        'review_text',
        'status',
        'rejection_reason',
        'moderated_at',
        'moderated_by',
        'helpful_count',
        'not_helpful_count',
    ];

    protected function casts(): array
    {
        return [
            'overall_rating'         => 'decimal:1',
            'product_quality_rating' => 'decimal:1',
            'delivery_rating'        => 'decimal:1',
            'service_rating'         => 'decimal:1',
            'status'                 => ReviewStatus::class,
            'helpful_count'          => 'integer',
            'not_helpful_count'      => 'integer',
            'moderated_at'           => 'datetime',
        ];
    }

    // Relationships

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
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

    public function scopeByTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
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
}
