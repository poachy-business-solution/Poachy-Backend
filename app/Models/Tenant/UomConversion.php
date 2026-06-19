<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class UomConversion extends Model
{
    use HasFactory;

    protected $table = 'uom_conversions';

    protected $fillable = [
        'from_uom_id',
        'to_uom_id',
        'conversion_factor',
    ];

    protected $casts = [
        'conversion_factor' => 'decimal:6',
    ];

    protected static function boot()
    {
        parent::boot();

        // Validate before creating/updating
        static::saving(function (UomConversion $conversion) {
            $conversion->validateConversion();
        });
    }

    // Relationships

    public function fromUom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'from_uom_id');
    }

    public function toUom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'to_uom_id');
    }

    // Scopes

    public function scopeBetween(Builder $query, int $fromUomId, int $toUomId): Builder
    {
        return $query->where('from_uom_id', $fromUomId)
            ->where('to_uom_id', $toUomId);
    }

    public function scopeBidirectional(Builder $query, int $uomId1, int $uomId2): Builder
    {
        return $query->where(function ($q) use ($uomId1, $uomId2) {
            $q->where('from_uom_id', $uomId1)->where('to_uom_id', $uomId2);
        })->orWhere(function ($q) use ($uomId1, $uomId2) {
            $q->where('from_uom_id', $uomId2)->where('to_uom_id', $uomId1);
        });
    }

    // Helper methods

    protected function validateConversion(): void
    {
        // Prevent self-conversion
        if ($this->from_uom_id === $this->to_uom_id) {
            throw new \Exception('Cannot create a conversion from a UOM to itself');
        }

        // Validate conversion factor is positive
        if ($this->conversion_factor <= 0) {
            throw new \Exception('Conversion factor must be greater than zero');
        }

        // Load UOMs to check types
        $fromUom = $this->fromUom ?? UnitOfMeasure::find($this->from_uom_id);
        $toUom = $this->toUom ?? UnitOfMeasure::find($this->to_uom_id);

        if (!$fromUom || !$toUom) {
            throw new \Exception('Both UOMs must exist');
        }

        // Check types match
        if ($fromUom->type !== $toUom->type) {
            throw new \Exception("Cannot create conversion between different UOM types: {$fromUom->type} and {$toUom->type}");
        }

        // Check for duplicate (reverse conversion already exists)
        $existingReverse = static::where('from_uom_id', $this->to_uom_id)
            ->where('to_uom_id', $this->from_uom_id)
            ->when($this->exists, fn($q) => $q->where('id', '!=', $this->id))
            ->exists();

        if ($existingReverse) {
            throw new \Exception('A reverse conversion already exists between these UOMs');
        }
    }

    public function convert(float $quantity): float
    {
        return $quantity * $this->conversion_factor;
    }

    public function getReverseFactorAttribute(): float
    {
        return 1 / $this->conversion_factor;
    }

    public function convertReverse(float $quantity): float
    {
        return $quantity / $this->conversion_factor;
    }

    public function createReverseConversion(): ?UomConversion
    {
        // Check if reverse already exists
        $reverse = static::where('from_uom_id', $this->to_uom_id)
            ->where('to_uom_id', $this->from_uom_id)
            ->first();

        if ($reverse) {
            // Update if factor changed
            if (abs($reverse->conversion_factor - $this->reverse_factor) > 0.000001) {
                $reverse->update(['conversion_factor' => $this->reverse_factor]);
            }
            return $reverse;
        }

        // Create new reverse conversion
        return static::create([
            'from_uom_id' => $this->to_uom_id,
            'to_uom_id' => $this->from_uom_id,
            'conversion_factor' => $this->reverse_factor,
        ]);
    }

    public function getDescriptionAttribute(): string
    {
        $from = $this->fromUom;
        $to = $this->toUom;

        if (!$from || !$to) {
            return 'Unknown conversion';
        }

        return "1 {$from->code} = {$this->conversion_factor} {$to->code}";
    }

    public function involvesUom(int $uomId): bool
    {
        return $this->from_uom_id === $uomId || $this->to_uom_id === $uomId;
    }

    public function getOtherUomId(int $uomId): ?int
    {
        if ($this->from_uom_id === $uomId) {
            return $this->to_uom_id;
        }

        if ($this->to_uom_id === $uomId) {
            return $this->from_uom_id;
        }

        return null;
    }
}
