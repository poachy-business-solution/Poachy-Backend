<?php

namespace App\Services\Tenant\Billing;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Tells central which branches sold on which days (Africa/Nairobi), for trading-day billing.
 *
 * The day is the sale's own time (`sale_date`, which offline sales set to when they were rung up),
 * not when it synced — so the report always covers the last LOOK_BACK_DAYS days plus today and is
 * re-sent hourly; late offline syncs simply show up in a later report. Reports are idempotent
 * upserts on central, and central's 200 response is the acknowledgement.
 */
class SaleDaysReporter
{
    public const LOOK_BACK_DAYS = 7;

    /** Nairobi is UTC+3 all year (no daylight saving); sale_date is stored in UTC. */
    private const NAIROBI_OFFSET_HOURS = 3;

    /**
     * Branch-days with sales in the window, oldest first.
     *
     * @return array<int, array{store_id: int, date: string, sales_count: int, is_main_branch: bool}>
     */
    public function collect(?CarbonInterface $now = null): array
    {
        $today = ($now ?? now())->copy()->setTimezone('Africa/Nairobi')->startOfDay();
        $fromUtc = $today->copy()->subDays(self::LOOK_BACK_DAYS)->setTimezone('UTC');
        $offset = self::NAIROBI_OFFSET_HOURS;

        return DB::table('sales')
            ->join('stores', 'stores.id', '=', 'sales.store_id')
            ->whereNull('sales.deleted_at')
            ->where('sales.sale_date', '>=', $fromUtc)
            ->groupBy('sales.store_id', 'stores.is_main_store', DB::raw("DATE(DATE_ADD(sales.sale_date, INTERVAL {$offset} HOUR))"))
            ->orderBy(DB::raw("DATE(DATE_ADD(sales.sale_date, INTERVAL {$offset} HOUR))"))
            ->orderBy('sales.store_id')
            ->get([
                'sales.store_id',
                'stores.is_main_store',
                DB::raw("DATE(DATE_ADD(sales.sale_date, INTERVAL {$offset} HOUR)) as local_date"),
                DB::raw('COUNT(*) as sales_count'),
            ])
            ->map(fn ($row) => [
                'store_id' => (int) $row->store_id,
                'date' => (string) $row->local_date,
                'sales_count' => (int) $row->sales_count,
                'is_main_branch' => (bool) $row->is_main_store,
            ])
            ->all();
    }

    /**
     * Send the current window to central. Returns how many branch-days central accepted.
     *
     * @throws RuntimeException when central refuses or cannot be reached
     */
    public function report(string $tenantId, ?CarbonInterface $now = null): int
    {
        $days = $this->collect($now);

        $response = Http::timeout((int) config('services.central_api.timeout', 60))
            ->acceptJson()
            ->withToken((string) config('services.central_api.token'))
            ->withHeaders(['X-Tenant-ID' => $tenantId])
            ->post(rtrim((string) config('services.central_api.url'), '/').'/api/v1/central/sync/inbound/sale-days', [
                'tenant_id' => $tenantId,
                'days' => $days,
            ]);

        if (! $response->successful() || $response->json('success') !== true) {
            throw new RuntimeException("Central refused the sale-days report: {$response->status()} {$response->body()}");
        }

        return (int) $response->json('data.accepted');
    }
}
