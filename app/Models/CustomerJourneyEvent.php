<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerJourneyEvent extends Model
{
    protected $connection = 'central';

    protected $table = 'customer_journey_events';

    public $timestamps = false;

    protected $fillable = [
        'customer_id',
        'session_id',
        'event_type',
        'event_category',
        'page_url',
        'page_title',
        'referrer_url',
        'related_entity_type',
        'related_entity_id',
        'marketplace_product_id',
        'marketplace_category_id',
        'tenant_id',
        'event_properties',
        'device_type',
        'browser',
        'platform',
        'ip_address',
        'city',
        'county',
        'event_timestamp',
        'time_on_page_seconds',
        'session_uuid',
        'sequence_in_session',
    ];

    protected function casts(): array
    {
        return [
            'event_properties'    => 'array',
            'event_timestamp'     => 'datetime',
            'time_on_page_seconds' => 'integer',
            'sequence_in_session' => 'integer',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function customer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCustomer::class, 'customer_id');
    }

    public function marketplaceProduct(): BelongsTo
    {
        return $this->belongsTo(MarketplaceProduct::class, 'marketplace_product_id');
    }

    public function marketplaceCategory(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCategory::class, 'marketplace_category_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    public function scopeBySession(Builder $query, string $sessionUuid): Builder
    {
        return $query->where('session_uuid', $sessionUuid);
    }

    public function scopeByEventType(Builder $query, string $type): Builder
    {
        return $query->where('event_type', $type);
    }

    public function scopeByDateRange(Builder $query, $start, $end): Builder
    {
        return $query->whereBetween('event_timestamp', [$start, $end]);
    }

    public function scopeByCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    // =========================================================================
    // Static Helpers
    // =========================================================================

    public static function track(array $data): self
    {
        // Auto-set event_timestamp if not provided
        if (! isset($data['event_timestamp'])) {
            $data['event_timestamp'] = now();
        }

        // Auto-set session_uuid from session_id if not provided
        if (! isset($data['session_uuid']) && isset($data['session_id'])) {
            $data['session_uuid'] = $data['session_id'];
        }

        // Auto-increment sequence_in_session if not provided
        if (! isset($data['sequence_in_session']) && isset($data['session_uuid'])) {
            $maxSequence = self::on('central')
                ->where('session_uuid', $data['session_uuid'])
                ->max('sequence_in_session') ?? 0;

            $data['sequence_in_session'] = $maxSequence + 1;
        }

        return self::create($data);
    }

    public static function getSessionEvents(string $sessionUuid): Collection
    {
        return self::on('central')
            ->where('session_uuid', $sessionUuid)
            ->orderBy('sequence_in_session')
            ->get();
    }
}
