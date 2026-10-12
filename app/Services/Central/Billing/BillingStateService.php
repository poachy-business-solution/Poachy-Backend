<?php

namespace App\Services\Central\Billing;

use App\Enums\Central\BillingStatus;
use App\Models\BillingAccount;
use App\Models\BillingDay;
use App\Models\BillingPrice;
use App\Models\TenantSaleDay;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Decides a shop's billing state and sends the matching notices.
 *
 *   trial    — inside the free trial (nothing charged)
 *   active   — balance above zero
 *   grace    — balance at or below zero: charges continue for `grace_days` (1) more day
 *   limited  — grace over and still unpaid: selling + sync keep working, reports, receiving,
 *              transfers and admin screens are blocked (EnforceBillingLimits)
 *
 * A top-up that brings the balance above zero restores full access at once. Low balance means
 * the balance covers fewer than `low_balance_days` (3) trading days at the shop's usual daily cost.
 */
class BillingStateService
{
    public function __construct(private BillingNotices $notices) {}

    /**
     * The shop's status for route checks. Read straight from the central row (indexed by
     * tenant_id): the cache is tenant-scoped by Stancl, so a top-up processed centrally could
     * not flush a cached value and the shop would stay blocked after paying.
     */
    public static function currentStatus(string $tenantId): ?BillingStatus
    {
        $value = BillingAccount::query()->where('tenant_id', $tenantId)->toBase()->value('status');

        return $value === null ? null : BillingStatus::from($value);
    }

    /** Re-evaluate one account; persists and notifies on changes. */
    public function evaluate(BillingAccount $account, ?CarbonInterface $now = null): BillingAccount
    {
        // UTC: Eloquent saves a Carbon's wall-clock time without converting its zone.
        $now = CarbonImmutable::instance($now ?? now())->utc();
        $price = BillingPrice::forDate($now->setTimezone('Africa/Nairobi'));
        $graceDays = $price?->grace_days ?? 1;
        $balance = (float) $account->balance;
        $previous = $account->status;

        if ($account->trial_ends_at !== null && $account->trial_ends_at->greaterThan($now)) {
            $next = BillingStatus::Trial;
            $changes = ['grace_until' => null, 'limited_since' => null];
        } elseif ($balance > 0) {
            $next = BillingStatus::Active;
            $changes = ['grace_until' => null, 'limited_since' => null];
        } elseif ($account->grace_until === null) {
            $next = BillingStatus::Grace;
            $changes = ['grace_until' => $now->addDays($graceDays), 'limited_since' => null];
        } elseif ($account->grace_until->greaterThan($now)) {
            $next = BillingStatus::Grace;
            $changes = [];
        } else {
            $next = BillingStatus::Limited;
            $changes = ['limited_since' => $account->limited_since ?? $now];
        }

        $account->forceFill(['status' => $next] + $changes)->save();

        if ($previous !== $next) {
            Cache::forget("tenant_access:{$account->tenant_id}");
            $this->notices->statusChanged($account, $previous, $next);
        }

        if ($next === BillingStatus::Active && $this->isLow($account, $price)) {
            $this->notices->lowBalance($account, $this->daysCovered($account, $price), $now);
        }

        return $account;
    }

    /** Re-evaluate every account (hourly, and after the nightly billing run). */
    public function evaluateAll(?CarbonInterface $now = null): int
    {
        $count = 0;
        BillingAccount::query()->orderBy('id')->each(function (BillingAccount $account) use ($now, &$count) {
            $this->evaluate($account, $now);
            $count++;
        });

        return $count;
    }

    /**
     * What a normal trading day costs this shop: first-branch rate plus the extra rate for
     * each other branch that sold in the last 7 days (at least one branch).
     */
    public function dailyCost(string $tenantId, ?BillingPrice $price = null): float
    {
        $price ??= BillingPrice::forDate(now('Africa/Nairobi'));
        if ($price === null) {
            return 0.0;
        }

        $branches = max(1, TenantSaleDay::query()
            ->where('tenant_id', $tenantId)
            ->where('date', '>=', now('Africa/Nairobi')->subDays(7)->toDateString())
            ->distinct()
            ->count('store_id'));

        return $price->dayRate(true) + ($branches - 1) * $price->dayRate(false);
    }

    /** Whole trading days the balance still covers (0 when at or below zero). */
    public function daysCovered(BillingAccount $account, ?BillingPrice $price = null): int
    {
        $cost = $this->dailyCost($account->tenant_id, $price);

        return $cost <= 0 ? 0 : max(0, (int) floor((float) $account->balance / $cost));
    }

    public function isLow(BillingAccount $account, ?BillingPrice $price = null): bool
    {
        $price ??= BillingPrice::forDate(now('Africa/Nairobi'));

        return (float) $account->balance > 0 && $this->daysCovered($account, $price) < ($price?->low_balance_days ?? 3);
    }

    /**
     * Days and amount billed in the current Nairobi month.
     *
     * @return array{days: int, amount: float}
     */
    public function monthToDate(string $tenantId): array
    {
        $today = now('Africa/Nairobi');
        $query = BillingDay::query()->where('tenant_id', $tenantId)
            ->whereBetween('date', [$today->copy()->startOfMonth()->toDateString(), $today->toDateString()]);

        return [
            'days' => (clone $query)->where('amount', '>', 0)->distinct()->count('date'),
            'amount' => round((float) $query->sum('amount'), 2),
        ];
    }
}
