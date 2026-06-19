<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAddress extends Model
{
    protected $connection = 'central';
    protected $table = 'customer_addresses';

    protected $fillable = [
        'customer_id',
        'address_type',
        'label',
        'recipient_name',
        'recipient_phone',
        'address_line',
        'building_apartment',
        'city',
        'county',
        'postal_code',
        'latitude',
        'longitude',
        'delivery_instructions',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'latitude'   => 'decimal:8',
            'longitude'  => 'decimal:8',
            'is_default' => 'boolean',
            'is_active'  => 'boolean',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function customer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCustomer::class, 'customer_id');
    }
}