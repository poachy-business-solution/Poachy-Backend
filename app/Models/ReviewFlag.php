<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ReviewFlag extends Model
{
    protected $connection = 'central';

    protected $table = 'review_flags';

    protected $fillable = [
        'customer_id',
        'flaggable_type',
        'flaggable_id',
        'reason',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCustomer::class, 'customer_id');
    }

    public function flaggable(): MorphTo
    {
        return $this->morphTo();
    }
}
