<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantCategoryMapping extends Model
{
    use HasFactory;

    protected $connection = 'central';
    protected $table = 'tenant_category_mappings';

    protected $fillable = [
        'tenant_id',
        'tenant_category_id',
        'tenant_category_name',
        'tenant_category_slug',
        'marketplace_category_id',
        'confidence_score',
        'is_auto_mapped',
        'is_verified',
    ];

    protected $casts = [
        'tenant_category_id' => 'integer',
        'marketplace_category_id' => 'integer',
        'confidence_score' => 'decimal:2',
        'is_auto_mapped' => 'boolean',
        'is_verified' => 'boolean',
    ];

    // Relationships

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function marketplaceCategory(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCategory::class, 'marketplace_category_id');
    }

    // Helper Methods

    public function needsVerification(): bool
    {
        return !$this->is_verified && $this->confidence_score < 80;
    }
}
