<?php

namespace App\Jobs\Central;

use App\Enums\Central\ReviewStatus;
use App\Models\TenantProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateTenantAggregateRatingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 60, 120];

    public function __construct(public readonly string $tenantId) {}

    public function handle(): void
    {
        Log::debug('UpdateTenantAggregateRatingJob: calculating ratings', [
            'tenant_id' => $this->tenantId,
        ]);

        // Calculate average ratings from approved reviews
        $aggregate = DB::connection('central')
            ->table('merchant_reviews')
            ->where('tenant_id', $this->tenantId)
            ->where('status', 'approved')
            ->whereNull('deleted_at')
            ->selectRaw('
                AVG(overall_rating) as avg_overall,
                AVG(product_quality_rating) as avg_product_quality,
                AVG(delivery_rating) as avg_delivery,
                AVG(service_rating) as avg_service,
                COUNT(*) as approved_count
            ')
            ->first();

        // Count total reviews (all statuses)
        $totalReviews = DB::connection('central')
            ->table('merchant_reviews')
            ->where('tenant_id', $this->tenantId)
            ->whereNull('deleted_at')
            ->count();

        // Count pending reviews
        $pendingReviews = DB::connection('central')
            ->table('merchant_reviews')
            ->where('tenant_id', $this->tenantId)
            ->where('status', ReviewStatus::Pending->value)
            ->whereNull('deleted_at')
            ->count();

        // Find or create tenant profile
        $profile = TenantProfile::on('central')->firstOrCreate(
            ['tenant_id' => $this->tenantId]
        );

        // Store old values for logging
        $oldValues = [
            'avg_overall'         => $profile->average_overall_rating,
            'avg_product_quality' => $profile->average_product_quality_rating,
            'avg_delivery'        => $profile->average_delivery_rating,
            'avg_service'         => $profile->average_service_rating,
            'total_reviews'       => $profile->total_reviews,
            'approved_reviews'    => $profile->approved_reviews,
            'pending_reviews'     => $profile->pending_reviews,
        ];

        // Update profile with new ratings
        $profile->update([
            'average_overall_rating'         => $aggregate->approved_count > 0
                ? round((float) $aggregate->avg_overall, 2)
                : null,
            'average_product_quality_rating' => $aggregate->approved_count > 0 && $aggregate->avg_product_quality
                ? round((float) $aggregate->avg_product_quality, 2)
                : null,
            'average_delivery_rating'        => $aggregate->approved_count > 0 && $aggregate->avg_delivery
                ? round((float) $aggregate->avg_delivery, 2)
                : null,
            'average_service_rating'         => $aggregate->approved_count > 0 && $aggregate->avg_service
                ? round((float) $aggregate->avg_service, 2)
                : null,
            'total_reviews'                  => $totalReviews,
            'approved_reviews'               => (int) $aggregate->approved_count,
            'pending_reviews'                => $pendingReviews,
            'ratings_last_calculated_at'     => now(),
        ]);

        Log::info('UpdateTenantAggregateRatingJob: updated tenant profile ratings', [
            'tenant_id'   => $this->tenantId,
            'old_values'  => $oldValues,
            'new_values'  => [
                'avg_overall'         => $profile->average_overall_rating,
                'avg_product_quality' => $profile->average_product_quality_rating,
                'avg_delivery'        => $profile->average_delivery_rating,
                'avg_service'         => $profile->average_service_rating,
                'total_reviews'       => $profile->total_reviews,
                'approved_reviews'    => $profile->approved_reviews,
                'pending_reviews'     => $profile->pending_reviews,
            ],
        ]);
    }
}
