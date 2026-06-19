<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantProfile extends Model
{
    protected $connection = 'central';

    protected $table = 'tenant_profiles';

    protected $fillable = [
        'tenant_id',
        'average_overall_rating',
        'average_product_quality_rating',
        'average_delivery_rating',
        'average_service_rating',
        'total_reviews',
        'approved_reviews',
        'pending_reviews',
        'total_orders',
        'completed_orders',
        'total_revenue',
        'total_marketplace_products',
        'active_marketplace_products',
        'ratings_last_calculated_at',
        'orders_last_calculated_at',
        'products_last_calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'average_overall_rating'          => 'decimal:2',
            'average_product_quality_rating'  => 'decimal:2',
            'average_delivery_rating'         => 'decimal:2',
            'average_service_rating'          => 'decimal:2',
            'total_reviews'                   => 'integer',
            'approved_reviews'                => 'integer',
            'pending_reviews'                 => 'integer',
            'total_orders'                    => 'integer',
            'completed_orders'                => 'integer',
            'total_revenue'                   => 'decimal:2',
            'total_marketplace_products'      => 'integer',
            'active_marketplace_products'     => 'integer',
            'ratings_last_calculated_at'      => 'datetime',
            'orders_last_calculated_at'       => 'datetime',
            'products_last_calculated_at'     => 'datetime',
        ];
    }

    // Relationships

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }
}
