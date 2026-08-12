<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductScaleBarcodeFormat extends Model
{
    protected $fillable = [
        'name',
        'prefix',
        'length',
        'product_code_start',
        'product_code_length',
        'value_start',
        'value_length',
        'value_type',
        'decimal_places',
        'checksum',
        'store_id',
        'is_active',
        'priority',
        'metadata',
        'notes',
    ];

    protected $casts = [
        'length' => 'integer',
        'product_code_start' => 'integer',
        'product_code_length' => 'integer',
        'value_start' => 'integer',
        'value_length' => 'integer',
        'decimal_places' => 'integer',
        'store_id' => 'integer',
        'is_active' => 'boolean',
        'priority' => 'integer',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'length' => 13,
        'value_type' => 'weight',
        'decimal_places' => 3,
        'is_active' => true,
        'priority' => 0,
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
