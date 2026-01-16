<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantBrandMapping extends Model
{
    use HasFactory;

    protected $connection = 'central';
    protected $table = 'tenant_brand_mappings';

    protected $fillable = [
        'tenant_id',
        'tenant_brand_id',
        'tenant_brand_name',
        'tenant_brand_slug',
        'marketplace_brand_id',
        'confidence_score',
        'is_auto_mapped',
        'is_verified',
    ];

    protected $casts = [
        'tenant_brand_id' => 'integer',
        'marketplace_brand_id' => 'integer',
        'confidence_score' => 'decimal:2',
        'is_auto_mapped' => 'boolean',
        'is_verified' => 'boolean',
    ];

    // Relationships

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function marketplaceBrand(): BelongsTo
    {
        return $this->belongsTo(MarketplaceBrand::class, 'marketplace_brand_id');
    }

    // Helper Methods

    public function needsVerification(): bool
    {
        return !$this->is_verified && $this->confidence_score < 80;
    }
}
