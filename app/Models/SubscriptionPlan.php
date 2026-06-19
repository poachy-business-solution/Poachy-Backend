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

    public function getBillingCycleDisplay(): string
    {
        if ($this->billing_cycle_days === 0) {
            return 'Lifetime';
        }

        if ($this->billing_cycle_days === 30) {
            return 'Monthly';
        }

        if ($this->billing_cycle_days === 365) {
            return 'Yearly';
        }

        if ($this->billing_cycle_days === 90) {
            return 'Quarterly';
        }

        return "{$this->billing_cycle_days} days";
    }

    public function getFeatureHighlights(): array
    {
        $features = $this->features ?? [];
        $highlights = [];

        // Products
        if (isset($features['max_products'])) {
            $highlights[] = [
                'category' => 'Products',
                'value' => $features['max_products'],
                'display' => $features['max_products'] === 'unlimited'
                    ? 'Unlimited Products'
                    : "Up to {$features['max_products']} Products",
            ];
        }

        // Users
        if (isset($features['max_users'])) {
            $highlights[] = [
                'category' => 'Users',
                'value' => $features['max_users'],
                'display' => $features['max_users'] === 'unlimited'
                    ? 'Unlimited Users'
                    : "Up to {$features['max_users']} Users",
            ];
        }

        // Locations
        if (isset($features['max_locations'])) {
            $highlights[] = [
                'category' => 'Locations',
                'value' => $features['max_locations'],
                'display' => $features['max_locations'] === 'unlimited'
                    ? 'Unlimited Locations'
                    : "{$features['max_locations']} Store Location" . ($features['max_locations'] > 1 ? 's' : ''),
            ];
        }

        // Transactions
        if (isset($features['max_transactions_per_month'])) {
            $highlights[] = [
                'category' => 'Transactions',
                'value' => $features['max_transactions_per_month'],
                'display' => $features['max_transactions_per_month'] === 'unlimited'
                    ? 'Unlimited Transactions'
                    : number_format($features['max_transactions_per_month']) . ' Transactions/Month',
            ];
        }

        // E-commerce
        if (isset($features['enable_ecommerce']) && $features['enable_ecommerce']) {
            $highlights[] = [
                'category' => 'E-commerce',
                'value' => true,
                'display' => 'Online Store Enabled',
            ];
        }

        // Marketplace
        if (isset($features['enable_marketplace']) && $features['enable_marketplace']) {
            $highlights[] = [
                'category' => 'Marketplace',
                'value' => true,
                'display' => 'Marketplace Access',
            ];
        }

        // Analytics
        if (isset($features['enable_analytics'])) {
            $analyticsLevel = ucfirst($features['enable_analytics']);
            $highlights[] = [
                'category' => 'Analytics',
                'value' => $features['enable_analytics'],
                'display' => "{$analyticsLevel} Analytics",
            ];
        }

        // Support
        if (isset($features['support'])) {
            $supportLevel = ucfirst($features['support']);
            $highlights[] = [
                'category' => 'Support',
                'value' => $features['support'],
                'display' => "{$supportLevel} Support",
            ];
        }

        // Transaction Fee
        if (isset($features['transaction_fee_percent'])) {
            $highlights[] = [
                'category' => 'Transaction Fee',
                'value' => $features['transaction_fee_percent'],
                'display' => $features['transaction_fee_percent'] == 0
                    ? 'No Transaction Fees'
                    : "{$features['transaction_fee_percent']}% Transaction Fee",
            ];
        }

        return $highlights;
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
