<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductBarcode extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'product_barcodes';

    protected $fillable = [
        'barcodeable_type',
        'barcodeable_id',
        'barcode',
        'barcode_type',
        'is_primary',
        'is_active',
        'supplier_id',
        'region',
        'store_id',
        'valid_from',
        'valid_until',
        'source',
        'metadata',
        'notes',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'barcode_type' => 'INTERNAL',
        'is_primary' => false,
        'is_active' => true,
        'source' => 'manual',
    ];

    public function barcodeable(): MorphTo
    {
        return $this->morphTo();
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeCurrentlyValid(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query
            ->where(fn (Builder $q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $today))
            ->where(fn (Builder $q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', $today));
    }

    public function scopeForBarcode(Builder $query, string $barcode): Builder
    {
        return $query->where('barcode', self::normalizeBarcode($barcode));
    }

    public static function normalizeBarcode(string $barcode): string
    {
        return trim($barcode);
    }
}
