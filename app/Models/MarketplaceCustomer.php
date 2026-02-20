<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\ProductReview;
use App\Models\MerchantReview;
use App\Models\ReviewVote;

class MarketplaceCustomer extends Model
{
    use SoftDeletes;

    protected $connection = 'central';
    protected $table = 'marketplace_customers';

    protected $fillable = [
        'user_id',
        'customer_number',
        'phone',
        'date_of_birth',
        'gender',
        'profile_picture',
        'is_active',
        'phone_verified',
        'phone_verified_at',
        'accepts_marketing',
        'accepts_sms',
        'last_login_at',
        'last_login_ip',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth'     => 'date',
            'is_active'         => 'boolean',
            'phone_verified'    => 'boolean',
            'phone_verified_at' => 'datetime',
            'accepts_marketing' => 'boolean',
            'accepts_sms'       => 'boolean',
            'last_login_at'     => 'datetime',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class, 'customer_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(MarketplaceOrder::class, 'customer_id');
    }

    public function carts(): HasMany
    {
        return $this->hasMany(ShoppingCart::class, 'customer_id');
    }

    public function productReviews(): HasMany
    {
        return $this->hasMany(ProductReview::class, 'customer_id');
    }

    public function merchantReviews(): HasMany
    {
        return $this->hasMany(MerchantReview::class, 'customer_id');
    }

    public function reviewVotes(): HasMany
    {
        return $this->hasMany(ReviewVote::class, 'customer_id');
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    public static function generateCustomerNumber(): string
    {
        $nextId = (static::on('central')->max('id') ?? 0) + 1;
        return 'MKT-CUST-' . str_pad($nextId, 6, '0', STR_PAD_LEFT);
    }
}