<?php

namespace App\Models;

use App\Enums\Central\DeliveryMethod;
use App\Enums\Central\DeliveryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceOrderDelivery extends Model
{
    protected $connection = 'central';

    protected $table = 'marketplace_order_deliveries';

    protected $fillable = [
        'order_id',
        'delivery_method',
        'zone_id',
        'zone_name',
        'delivery_fee',
        'delivery_status',
        'courier_company',
        'courier_name',
        'courier_phone',
        'tracking_number',
        'tracking_url',
        'estimated_pickup_time',
        'actual_pickup_time',
        'estimated_delivery_time',
        'actual_delivery_time',
        'delivery_proof_type',
        'delivery_proof_data',
        'received_by_name',
        'received_by_phone',
        'delivery_notes',
        'delivery_issues',
        'delivery_attempts',
        'last_latitude',
        'last_longitude',
        'last_location_update',
    ];

    protected function casts(): array
    {
        return [
            'delivery_method'         => DeliveryMethod::class,
            'delivery_status'         => DeliveryStatus::class,
            'zone_id'                 => 'integer',
            'delivery_fee'            => 'decimal:2',
            'estimated_pickup_time'   => 'datetime',
            'actual_pickup_time'      => 'datetime',
            'estimated_delivery_time' => 'datetime',
            'actual_delivery_time'    => 'datetime',
            'delivery_attempts'       => 'integer',
            'last_latitude'           => 'decimal:8',
            'last_longitude'          => 'decimal:8',
            'last_location_update'    => 'datetime',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function order(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrder::class, 'order_id');
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    public function updateStatus(DeliveryStatus $status): bool
    {
        return $this->update(['delivery_status' => $status]);
    }

    public function isDelivered(): bool
    {
        return $this->delivery_status === DeliveryStatus::Delivered;
    }

    public function updateLocation(float $latitude, float $longitude): bool
    {
        return $this->update([
            'last_latitude'       => $latitude,
            'last_longitude'      => $longitude,
            'last_location_update' => now(),
        ]);
    }
}
