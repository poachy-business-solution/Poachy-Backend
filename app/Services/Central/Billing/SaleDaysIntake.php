<?php

namespace App\Services\Central\Billing;

use App\Models\TenantSaleDay;
use Illuminate\Support\Facades\DB;

/**
 * Stores a shop's sale-days report: one row per (tenant, branch, Nairobi date), upserted so the
 * hourly re-reports are harmless and late offline sales raise the count in place.
 */
class SaleDaysIntake
{
    /**
     * @param  array<int, array{store_id: int, date: string, sales_count: int, is_main_branch: bool}>  $days
     */
    public function store(string $tenantId, array $days): int
    {
        if ($days === []) {
            return 0;
        }

        $now = now();
        $rows = array_map(fn (array $d) => [
            'tenant_id' => $tenantId,
            'store_id' => (int) $d['store_id'],
            'date' => $d['date'],
            'sales_count' => (int) $d['sales_count'],
            'is_main_branch' => (bool) $d['is_main_branch'],
            'reported_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ], $days);

        DB::connection('central')->transaction(function () use ($rows) {
            TenantSaleDay::query()->upsert(
                $rows,
                ['tenant_id', 'store_id', 'date'],
                ['sales_count', 'is_main_branch', 'reported_at', 'updated_at'],
            );
        });

        return count($rows);
    }
}
