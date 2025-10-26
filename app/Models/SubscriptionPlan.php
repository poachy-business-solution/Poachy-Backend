<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $connection = 'central';
    protected $table = 'subscription_plans';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'currency',
        'billing_cycle_days',
        'features',
        'is_active',
        'is_featured',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'billing_cycle_days' => 'integer',
        'features' => 'array',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
    ];

    // Relationships
    public function subscriptions()
    {
        return $this->hasMany(BusinessSubscription::class);
    }

    public function activeSubscriptions()
    {
        return $this->subscriptions()->where('status', 'active');
    }

    // Helper Methods
    public function isFree(): bool
    {
        return $this->price == 0;
    }

    public function getFeature(string $key, $default = null)
    {
        return $this->features[$key] ?? $default;
        // Example: {
            //   "max_products": 100,
            //   "max_users": 5,
            //   "max_locations": 1,
            //   "enable_ecommerce": true,
            //   "enable_marketplace": false,
            //   "enable_analytics": false,            
            // }
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }
}
