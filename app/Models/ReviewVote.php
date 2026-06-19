<?php

namespace App\Models;

use App\Enums\Central\ReviewVoteType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ReviewVote extends Model
{
    protected $connection = 'central';

    protected $table = 'review_votes';

    protected $fillable = [
        'customer_id',
        'voteable_type',
        'voteable_id',
        'vote_type',
    ];

    protected function casts(): array
    {
        return [
            'vote_type' => ReviewVoteType::class,
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCustomer::class, 'customer_id');
    }

    public function voteable(): MorphTo
    {
        return $this->morphTo();
    }
}
