<?php

namespace App\Models\Tenant;

use App\Observers\Tenant\ProductUomObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([ProductUomObserver::class])]
class ProductUom extends Model
{
    use HasFactory;

    protected $table = 'product_uoms';

    protected $fillable = [
        'product_id',
        'uom_id',
        'is_base_uom',
        'is_purchase_uom',
        'is_sales_uom',
        'is_inventory_uom',
        'conversion_to_base',
    ];

    protected $casts = [
        'is_base_uom' => 'boolean',
        'is_purchase_uom' => 'boolean',
        'is_sales_uom' => 'boolean',
        'is_inventory_uom' => 'boolean',
        'conversion_to_base' => 'decimal:6',
    ];

    protected $attributes = [
        'is_base_uom' => false,
        'is_purchase_uom' => true,
        'is_sales_uom' => true,
        'is_inventory_uom' => true,
        'conversion_to_base' => 1,
    ];

    // Relationships

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    // Scopes

    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeBaseUom($query)
    {
        return $query->where('is_base_uom', true);
    }

    public function scopePurchaseUoms($query)
    {
        return $query->where('is_purchase_uom', true);
    }

    public function scopeSalesUoms($query)
    {
        return $query->where('is_sales_uom', true);
    }

    public function scopeInventoryUoms($query)
    {
        return $query->where('is_inventory_uom', true);
    }

    // Helper Methods

    public function isBaseUom(): bool
    {
        return $this->is_base_uom === true;
    }

    public function canBePurchasedIn(): bool
    {
        return $this->is_purchase_uom === true;
    }

    public function canBeSoldIn(): bool
    {
        return $this->is_sales_uom === true;
    }

    public function canTrackInventoryIn(): bool
    {
        return $this->is_inventory_uom === true;
    }

    /**
     * Convert quantity from this UOM to base UOM
     */
    public function convertToBase(float $quantity): float
    {
        return $quantity * $this->conversion_to_base;
    }

    /**
     * Convert quantity from base UOM to this UOM
     */
    public function convertFromBase(float $quantity): float
    {
        if ($this->conversion_to_base == 0) {
            throw new \RuntimeException('Cannot convert: conversion_to_base is 0');
        }

        return $quantity / $this->conversion_to_base;
    }

    /**
     * Get display name with UOM code
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->uom->name} ({$this->uom->code})";
    }

    /**
     * Get conversion description
     */
    public function getConversionDescriptionAttribute(): string
    {
        if ($this->is_base_uom) {
            return 'Base Unit';
        }

        return "1 {$this->uom->code} = {$this->conversion_to_base} base units";
    }
}
