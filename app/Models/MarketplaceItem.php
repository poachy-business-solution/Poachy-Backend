<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One product as buyers think of it; every shop's listing of it is an offer. */
class MarketplaceItem extends Model
{
    protected $connection = 'central';

    protected $fillable = ['name', 'slug', 'barcode', 'marketplace_category_id', 'marketplace_brand_id', 'primary_image', 'description', 'created_by_admin'];

    public function offers(): HasMany
    {
        return $this->hasMany(MarketplaceProduct::class, 'marketplace_item_id');
    }

    public function marketplaceCategory(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCategory::class, 'marketplace_category_id');
    }

    public function marketplaceBrand(): BelongsTo
    {
        return $this->belongsTo(MarketplaceBrand::class, 'marketplace_brand_id');
    }
}
