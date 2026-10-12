<?php

namespace App\Services\Central\Billing;

use App\Enums\Central\BillingLedgerType;
use App\Models\BillingAccount;
use App\Models\BillingDay;
use App\Models\BillingPrice;
use App\Models\TenantSaleDay;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Nightly trading-day billing (runs 01:00 Africa/Nairobi).
 *
 * For every reported branch-day in the window (today − 7 … yesterday, Nairobi) that has no
 * billing_days row yet, charge the day rate — reduced so the branch never passes its monthly
 * cap — and write a ledger line. Rules:
 *  - The shop's main branch pays the first-branch rate and cap; every other branch the
 *    extra-branch rate and cap. If no reported branch is flagged main, the lowest store id is.
 *  - Caps are per branch per calendar month (the billed day's month).
 *  - Days inside the free trial are recorded at KSh 0, so they are never charged later.
 *  - (tenant, store, date) is unique, so re-runs and overlapping runs cannot bill twice.
 *  - Today is never billed: its sales may still be coming in; it is billed tomorrow night.
 */
class NightlyBillingService
{
    public const LOOK_BACK_DAYS = 7;

    public function __construct(private BillingLedgerService $ledger) {}

    /**
     * Bill every shop. Returns totals for logging.
     *
     * @return array{tenants: int, days_billed: int, amount: float}
     */
    public function run(?CarbonInterface $now = null): array
    {
        $today = CarbonImmutable::instance($now ?? now())->setTimezone('Africa/Nairobi')->startOfDay();
        $totals = ['tenants' => 0, 'days_billed' => 0, 'amount' => 0.0];

        $tenantIds = TenantSaleDay::query()
            ->whereBetween('date', [$today->subDays(self::LOOK_BACK_DAYS)->toDateString(), $today->subDay()->toDateString()])
            ->distinct()
            ->pluck('tenant_id');

        foreach ($tenantIds as $tenantId) {
            $result = $this->billTenant((string) $tenantId, $today);
            $totals['tenants']++;
            $totals['days_billed'] += $result['days_billed'];
            $totals['amount'] += $result['amount'];
        }

        Log::info('Nightly billing run finished', $totals + ['date' => $today->toDateString()]);

        return $totals;
    }

    /**
     * @return array{days_billed: int, amount: float}
     */
    public function billTenant(string $tenantId, CarbonImmutable $today): array
    {
        $account = BillingAccount::query()->where('tenant_id', $tenantId)->first();

        if ($account === null) {
            Log::warning('Nightly billing: shop has sale days but no billing account', ['tenant_id' => $tenantId]);

            return ['days_billed' => 0, 'amount' => 0.0];
        }

        $from = $today->subDays(self::LOOK_BACK_DAYS)->toDateString();
        $to = $today->subDay()->toDateString();

        $saleDays = TenantSaleDay::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->orderBy('store_id')
            ->get();

        $alreadyBilled = BillingDay::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('date', [$from, $to])
            ->get()
            ->map(fn (BillingDay $d) => $d->store_id.'|'.$d->date->toDateString())
            ->flip();

        $mainStoreId = $this->mainStoreId($tenantId, $saleDays);
        $billed = 0;
        $amount = 0.0;

        foreach ($saleDays as $day) {
            $date = $day->date->toDateString();

            if ($alreadyBilled->has($day->store_id.'|'.$date)) {
                continue;
            }

            $charged = $this->billDay($account, (int) $day->store_id, CarbonImmutable::parse($date, 'Africa/Nairobi'), (int) $day->store_id === $mainStoreId);

            if ($charged !== null) {
                $billed++;
                $amount += $charged;
            }
        }

        return ['days_billed' => $billed, 'amount' => round($amount, 2)];
    }

    /**
     * Bill one branch-day. Returns the amount charged (0 in trial or at the cap), or null when
     * another run got there first.
     */
    private function billDay(BillingAccount $account, int $storeId, CarbonImmutable $date, bool $isMain): ?float
    {
        $price = BillingPrice::forDate($date);

        if ($price === null) {
            Log::error('Nightly billing: no price list for date', ['date' => $date->toDateString()]);

            return null;
        }

        $inTrial = $account->trial_ends_at !== null && $account->trial_ends_at->greaterThanOrEqualTo($date->endOfDay());

        $billedThisMonth = (float) BillingDay::query()
            ->where('tenant_id', $account->tenant_id)
            ->where('store_id', $storeId)
            ->whereBetween('date', [$date->startOfMonth()->toDateString(), $date->endOfMonth()->toDateString()])
            ->sum('amount');

        $charge = $inTrial ? 0.0 : max(0.0, min($price->dayRate($isMain), $price->monthlyCap($isMain) - $billedThisMonth));
        $charge = round($charge, 2);

        try {
            return DB::connection('central')->transaction(function () use ($account, $storeId, $date, $isMain, $charge, $inTrial, $price) {
                $billingDay = BillingDay::query()->create([
                    'tenant_id' => $account->tenant_id,
                    'store_id' => $storeId,
                    'date' => $date->toDateString(),
                    'amount' => $charge,
                    'is_main_branch' => $isMain,
                ]);

                if ($charge > 0) {
                    $entry = $this->ledger->post(
                        account: $account,
                        type: BillingLedgerType::Charge,
                        amount: -$charge,
                        description: sprintf('Trading day %s · %s', $date->format('D j M'), $isMain ? 'main branch' : 'branch '.$storeId),
                        source: 'system',
                        reference: $date->toDateString(),
                        idempotencyKey: "charge:{$account->tenant_id}:{$storeId}:{$date->toDateString()}",
                        meta: [
                            'store_id' => $storeId,
                            'date' => $date->toDateString(),
                            'is_main_branch' => $isMain,
                            'day_rate' => $price->dayRate($isMain),
                            'capped' => $charge < $price->dayRate($isMain),
                        ],
                    );
                    $billingDay->forceFill(['billing_ledger_id' => $entry->id])->save();
                }

                if ($inTrial) {
                    Log::debug('Nightly billing: trial day recorded at 0', ['tenant_id' => $account->tenant_id, 'store_id' => $storeId, 'date' => $date->toDateString()]);
                }

                return $charge;
            });
        } catch (UniqueConstraintViolationException) {
            return null; // billed by an overlapping run
        }
    }

    /**
     * @param  Collection<int, TenantSaleDay>  $windowDays
     */
    private function mainStoreId(string $tenantId, Collection $windowDays): ?int
    {
        $flagged = TenantSaleDay::query()->where('tenant_id', $tenantId)->where('is_main_branch', true)->orderByDesc('date')->value('store_id');

        return $flagged !== null ? (int) $flagged : ($windowDays->min('store_id') ?? null);
    }
}
