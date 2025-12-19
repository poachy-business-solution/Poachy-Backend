<?php

namespace App\Models\Tenant;

use App\Enums\Tenant\UomSourceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class UnitOfMeasure extends Model
{
    use HasFactory;

    protected $table = 'units_of_measure';

    protected $fillable = [
        'code',
        'name',
        'type',
        'source_type',
        'is_base_unit',
        'is_active',
        'description',
    ];

    protected $casts = [
        'is_base_unit' => 'boolean',
        'is_active' => 'boolean',
        'source_type' => UomSourceType::class,
    ];

    // Relationships

    public function conversionsFrom(): HasMany
    {
        return $this->hasMany(UomConversion::class, 'from_uom_id');
    }

    public function conversionsTo(): HasMany
    {
        return $this->hasMany(UomConversion::class, 'to_uom_id');
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeBaseUnits(Builder $query): Builder
    {
        return $query->where('is_base_unit', true);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeSystem(Builder $query): Builder
    {
        return $query->where('source_type', UomSourceType::SYSTEM);
    }

    public function scopeCustom(Builder $query): Builder
    {
        return $query->where('source_type', UomSourceType::CUSTOM);
    }

    // Helper Methods

    public function convertTo(float $quantity, UnitOfMeasure $targetUom): float
    {
        // Same UOM, no conversion needed
        if ($this->id === $targetUom->id) {
            return $quantity;
        }

        // Check if types match
        if ($this->type !== $targetUom->type) {
            throw new \Exception("Cannot convert between different UOM types: {$this->type} and {$targetUom->type}");
        }

        // Find direct conversion
        $conversion = UomConversion::where('from_uom_id', $this->id)
            ->where('to_uom_id', $targetUom->id)
            ->first();

        if ($conversion) {
            return $quantity * $conversion->conversion_factor;
        }

        // Try reverse conversion
        $reverseConversion = UomConversion::where('from_uom_id', $targetUom->id)
            ->where('to_uom_id', $this->id)
            ->first();

        if ($reverseConversion) {
            return $quantity / $reverseConversion->conversion_factor;
        }

        // If no direct conversion, try via base unit
        return $this->convertViaBaseUnit($quantity, $targetUom);
    }

    protected function convertViaBaseUnit(float $quantity, UnitOfMeasure $targetUom): float
    {
        // Get base unit for this type
        $baseUnit = static::where('type', $this->type)
            ->where('is_base_unit', true)
            ->first();

        if (!$baseUnit) {
            throw new \Exception("No base unit defined for type: {$this->type}");
        }

        // Convert to base unit first
        $quantityInBase = $this->convertToBase($quantity, $baseUnit);

        // Then convert from base to target
        return $targetUom->convertFromBase($quantityInBase, $baseUnit);
    }

    protected function convertToBase(float $quantity, UnitOfMeasure $baseUnit): float
    {
        if ($this->id === $baseUnit->id) {
            return $quantity;
        }

        $conversion = UomConversion::where('from_uom_id', $this->id)
            ->where('to_uom_id', $baseUnit->id)
            ->first();

        if ($conversion) {
            return $quantity * $conversion->conversion_factor;
        }

        $reverseConversion = UomConversion::where('from_uom_id', $baseUnit->id)
            ->where('to_uom_id', $this->id)
            ->first();

        if ($reverseConversion) {
            return $quantity / $reverseConversion->conversion_factor;
        }

        throw new \Exception("Cannot convert {$this->code} to base unit {$baseUnit->code}");
    }

    protected function convertFromBase(float $quantity, UnitOfMeasure $baseUnit): float
    {
        if ($this->id === $baseUnit->id) {
            return $quantity;
        }

        $conversion = UomConversion::where('from_uom_id', $baseUnit->id)
            ->where('to_uom_id', $this->id)
            ->first();

        if ($conversion) {
            return $quantity * $conversion->conversion_factor;
        }

        $reverseConversion = UomConversion::where('from_uom_id', $this->id)
            ->where('to_uom_id', $baseUnit->id)
            ->first();

        if ($reverseConversion) {
            return $quantity / $reverseConversion->conversion_factor;
        }

        throw new \Exception("Cannot convert from base unit {$baseUnit->code} to {$this->code}");
    }

    public function canConvertTo(UnitOfMeasure $targetUom): bool
    {
        // Same UOM
        if ($this->id === $targetUom->id) {
            return true;
        }

        // Different types cannot convert
        if ($this->type !== $targetUom->type) {
            return false;
        }

        // Check if conversion exists
        return UomConversion::where(function ($query) use ($targetUom) {
            $query->where('from_uom_id', $this->id)
                ->where('to_uom_id', $targetUom->id);
        })->orWhere(function ($query) use ($targetUom) {
            $query->where('from_uom_id', $targetUom->id)
                ->where('to_uom_id', $this->id);
        })->exists();
    }

    public function getBaseUnit(): ?UnitOfMeasure
    {
        if ($this->is_base_unit) {
            return $this;
        }

        return static::where('type', $this->type)
            ->where('is_base_unit', true)
            ->first();
    }

    public function getDisplayNameAttribute(): string
    {
        return "{$this->name} ({$this->code})";
    }

    public function isSystem(): bool
    {
        return $this->source_type === UomSourceType::SYSTEM;
    }

    public function isCustom(): bool
    {
        return $this->source_type === UomSourceType::CUSTOM;
    }
}
