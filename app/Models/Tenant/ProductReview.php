<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductReview extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'central_review_id', 'product_id', 'product_name', 'product_sku',
        'customer_name', 'rating', 'title', 'review_text', 'review_images',
        'is_verified_purchase', 'merchant_response', 'merchant_responded_at',
        'response_sync_status', 'status', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'rating'                => 'decimal:1',
            'review_images'         => 'array',
            'is_verified_purchase'  => 'boolean',
            'reviewed_at'           => 'datetime',
            'merchant_responded_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function canReceiveMerchantResponse(): bool
    {
        return is_null($this->merchant_response);
    }

    public function isMerchantResponseEditable(): bool
    {
        return ! is_null($this->merchant_responded_at)
            && $this->merchant_responded_at->diffInHours(now()) <= 24;
    }
}
