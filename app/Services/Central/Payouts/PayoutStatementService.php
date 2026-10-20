<?php

namespace App\Services\Central\Payouts;

use App\Models\MarketplacePayout;
use App\Models\MarketplacePayoutLine;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A shop's view of its money: balance (held / available), payouts and their statements, and
 * the earnings totals the app's Marketplace tab shows. Always scoped to one tenant.
 */
class PayoutStatementService
{
    /**
     * @return array{held: float, available: float, next_payout_at: string, payout_phone: string|null}
     */
    public function balance(string $tenantId, ?string $payoutPhone): array
    {
        $now = now();

        return [
            'held' => round((float) MarketplacePayoutLine::query()->where('tenant_id', $tenantId)->held($now)->sum('net'), 2),
            'available' => round((float) MarketplacePayoutLine::query()->where('tenant_id', $tenantId)->available($now)->sum('net'), 2),
            'next_payout_at' => $this->nextRun($now)->toIso8601String(),
            'payout_phone' => $payoutPhone ? $this->mask($payoutPhone) : null,
        ];
    }

    public function payouts(string $tenantId, int $perPage = 20): LengthAwarePaginator
    {
        return MarketplacePayout::query()->where('tenant_id', $tenantId)->latest('id')->paginate($perPage);
    }

    public function find(string $tenantId, int $id): ?MarketplacePayout
    {
        return MarketplacePayout::query()->where('tenant_id', $tenantId)->with(['lines' => fn ($q) => $q->orderBy('id')])->find($id);
    }

    /**
     * Last 30 days, by handover / payout date.
     *
     * @return array{gross_30d: float, fees_30d: float, commission_30d: float, clawbacks_30d: float, net_30d: float, paid_out_30d: float, held: float, available: float, payouts_enabled: bool}
     */
    public function earnings(string $tenantId): array
    {
        $since = now()->subDays(30);
        $sales = MarketplacePayoutLine::query()->where('tenant_id', $tenantId)->where('type', MarketplacePayoutLine::SALE)->where('handed_over_at', '>=', $since)
            ->select(DB::raw('COALESCE(SUM(gross),0) g, COALESCE(SUM(processing_fee),0) f, COALESCE(SUM(commission_amount),0) c, COALESCE(SUM(net),0) n'))->first();
        $clawbacks = (float) MarketplacePayoutLine::query()->where('tenant_id', $tenantId)->where('type', MarketplacePayoutLine::CLAWBACK)->where('created_at', '>=', $since)->sum('net');
        $paidOut = (float) MarketplacePayout::query()->where('tenant_id', $tenantId)->where('status', MarketplacePayout::PAID)->where('paid_at', '>=', $since)->sum('amount');
        $balance = $this->balance($tenantId, null);

        return [
            'gross_30d' => round((float) $sales->g, 2),
            'fees_30d' => round((float) $sales->f, 2),
            'commission_30d' => round((float) $sales->c, 2),
            'clawbacks_30d' => round(abs($clawbacks), 2),
            'net_30d' => round((float) $sales->n + $clawbacks, 2),
            'paid_out_30d' => round($paidOut, 2),
            'held' => $balance['held'],
            'available' => $balance['available'],
            'payouts_enabled' => true,
        ];
    }

    /** 02:00 Nairobi, today or tomorrow. */
    public function nextRun(Carbon $now): Carbon
    {
        $run = $now->copy()->setTimezone('Africa/Nairobi')->setTime(2, 0);
        if ($run->lte($now)) {
            $run->addDay();
        }

        return $run;
    }

    private function mask(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        return strlen($digits) > 7 ? substr($digits, 0, 4).'•••'.substr($digits, -3) : $digits;
    }
}
