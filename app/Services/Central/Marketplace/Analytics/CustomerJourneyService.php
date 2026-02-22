<?php

namespace App\Services\Central\Marketplace\Analytics;

use App\Models\CustomerJourneyEvent;

class CustomerJourneyService
{
    /**
     * Reconstruct customer session journey.
     */
    public function getSessionJourney(string $sessionUuid): array
    {
        return CustomerJourneyEvent::on('central')
            ->where('session_uuid', $sessionUuid)
            ->orderBy('sequence_in_session')
            ->with(['marketplaceProduct:id,name', 'tenant:id,business_name'])
            ->get()
            ->map(function ($event) {
                return [
                    'event_type'       => $event->event_type,
                    'event_timestamp'  => $event->event_timestamp,
                    'product'          => $event->marketplaceProduct ? ['id' => $event->marketplaceProduct->id, 'name' => $event->marketplaceProduct->name] : null,
                    'tenant'           => $event->tenant ? ['id' => $event->tenant->id, 'name' => $event->tenant->business_name] : null,
                    'event_properties' => $event->event_properties,
                    'page_url'         => $event->page_url,
                ];
            })
            ->toArray();
    }

    /**
     * Get most common conversion paths.
     */
    public function getCommonConversionPaths(\DateTime $startDate, \DateTime $endDate, int $limit = 10): array
    {
        // Group events by session and concatenate event types to form paths
        $paths = CustomerJourneyEvent::on('central')
            ->whereBetween('event_timestamp', [$startDate, $endDate])
            ->whereIn('event_type', ['product_view', 'add_to_cart', 'checkout_started', 'purchase'])
            ->orderBy('session_uuid')
            ->orderBy('sequence_in_session')
            ->get()
            ->groupBy('session_uuid')
            ->map(function ($events) {
                return $events->pluck('event_type')->implode(' → ');
            })
            ->countBy()
            ->sortDesc()
            ->take($limit);

        return $paths->map(function ($count, $path) {
            return [
                'path'  => $path,
                'count' => $count,
            ];
        })->values()->toArray();
    }
}
