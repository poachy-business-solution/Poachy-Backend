<?php

namespace App\Models\Tenant;

use App\Observers\Tenant\TaxRateObserver;
use App\Traits\Tenant\HasAuditLogging;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([TaxRateObserver::class])]
class TaxRate extends Model
{
    use HasFactory, HasAuditLogging;

    protected $table = 'tax_rates';

    public $timestamps = false;

    protected $fillable = [
        'tax_name',
        'rate',
        'effective_from',
        'effective_until',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'effective_from' => 'date',
        'effective_until' => 'date',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'created_at' => 'datetime',
    ];

    protected $attributes = [
        'is_active' => true,
        'is_default' => false,
    ];

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeCurrentlyEffective($query)
    {
        $today = now()->toDateString();

        return $query->where('effective_from', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', $today);
            });
    }

    // Helper methods

    public function isCurrentlyEffective(): bool
    {
        $today = now()->toDateString();

        return $this->effective_from <= $today
            && ($this->effective_until === null || $this->effective_until >= $today);
    }
}
