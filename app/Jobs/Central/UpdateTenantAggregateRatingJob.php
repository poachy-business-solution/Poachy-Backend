<?php

namespace App\Jobs\Central;

use App\Models\Tenant;
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
        Log::debug('UpdateTenantAggregateRatingJob: checking reviews', [
            'tenant_id' => $this->tenantId,
        ]);

        // Debug: Check what reviews exist
        $allReviews = DB::connection('central')
            ->table('merchant_reviews')
            ->where('tenant_id', $this->tenantId)
            ->whereNull('deleted_at')
            ->select('id', 'status', 'overall_rating')
            ->get();

        Log::debug('UpdateTenantAggregateRatingJob: found reviews', [
            'tenant_id'        => $this->tenantId,
            'total_reviews'    => $allReviews->count(),
            'approved_reviews' => $allReviews->where('status', 'approved')->count(),
            'statuses'         => $allReviews->pluck('status')->unique()->toArray(),
        ]);

        $aggregate = DB::connection('central')
            ->table('merchant_reviews')
            ->where('tenant_id', $this->tenantId)
            ->where('status', 'approved')
            ->whereNull('deleted_at')
            ->selectRaw('AVG(overall_rating) as average_rating, COUNT(*) as review_count')
            ->first();

        $tenant = Tenant::on('central')->find($this->tenantId);

        if (! $tenant) {
            Log::warning('UpdateTenantAggregateRatingJob: tenant not found', [
                'tenant_id' => $this->tenantId,
            ]);

            return;
        }

        $tenant->update([
            'average_rating' => $aggregate->review_count > 0
                ? round((float) $aggregate->average_rating, 2)
                : null,
            'review_count' => (int) $aggregate->review_count,
        ]);

        Log::info('UpdateTenantAggregateRatingJob: updated tenant rating aggregate', [
            'tenant_id'      => $this->tenantId,
            'average_rating' => $tenant->average_rating,
            'review_count'   => $tenant->review_count,
        ]);
    }
}
