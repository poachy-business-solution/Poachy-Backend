<?php

namespace App\Services\Tenant\Reports;

use App\Enums\Tenant\RefundStatus;
use App\Enums\Tenant\ShiftStatus;
use App\Models\Tenant\Sale;
use App\Models\Tenant\SaleItem;
use App\Models\Tenant\SaleRefund;
use App\Models\Tenant\ShiftAssignment;
use App\Models\Tenant\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * How each person at the counter did: what they sold, how much haggling
 * added above the list price, what they sold below it or gave away, the
 * refunds they paid out, and whether their till balanced.
 */
class StaffReportService
{
    public function __construct(protected OwnerOverviewService $overviews) {}

    /**
     * @return array{period: array{from: string, to: string}, staff: array<int, array<string, mixed>>}
     */
    public function forPeriod(string $from, string $to, ?int $storeId = null, ?int $userId = null): array
    {
        $from = CarbonImmutable::parse($from);
        $to = CarbonImmutable::parse($to);
        [$start, $end] = $this->overviews->bounds($from, $to);

        $sales = Sale::query()
            ->whereBetween('sale_date', [$start, $end])
            ->when($storeId, fn (Builder $query) => $query->where('store_id', $storeId))
            ->when($userId, fn (Builder $query) => $query->where('served_by', $userId));

        $salesByUser = (clone $sales)
            ->selectRaw('served_by, COUNT(*) as sales_count, SUM(total_amount) as sales_amount')
            ->groupBy('served_by')
            ->get()
            ->keyBy('served_by');

        $items = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereIn('sale_items.sale_id', (clone $sales)->select('id'))
            ->get(['sales.served_by', 'sale_items.quantity', 'sale_items.unit_price', 'sale_items.list_unit_price', 'sale_items.is_giveaway'])
            ->groupBy('served_by');

        $refunds = SaleRefund::query()
            ->where('status', RefundStatus::COMPLETED)
            ->whereBetween('created_at', [$start, $end])
            ->when($storeId, fn (Builder $query) => $query->where('store_id', $storeId))
            ->when($userId, fn (Builder $query) => $query->where('processed_by', $userId))
            ->selectRaw('processed_by, COUNT(*) as refunds_count, SUM(refund_amount) as refunds_amount')
            ->groupBy('processed_by')
            ->get()
            ->keyBy('processed_by');

        $tills = ShiftAssignment::query()
            ->whereIn('status', [ShiftStatus::IN_PROGRESS, ShiftStatus::COMPLETED])
            ->whereNotNull('actual_start')
            ->whereBetween('shift_date', [$from->toDateString(), $to->toDateString()])
            ->when($storeId, fn (Builder $query) => $query->where('store_id', $storeId))
            ->when($userId, fn (Builder $query) => $query->where('user_id', $userId))
            ->orderBy('actual_start')
            ->get()
            ->groupBy('user_id');

        $userIds = collect($salesByUser->keys())
            ->merge($refunds->keys())
            ->merge($tills->keys())
            ->filter()
            ->unique()
            ->values();

        $users = User::query()->whereIn('id', $userIds)->with('roles:id,name')->get()->keyBy('id');

        $staff = $userIds->map(function ($id) use ($users, $salesByUser, $items, $refunds, $tills) {
            $user = $users->get($id);
            $pricing = $this->pricing($items->get($id, collect()));

            return [
                'user_id' => (int) $id,
                'name' => $user?->name ?? 'Unknown',
                'role' => $user?->roles->pluck('name')->first(),
                'sales_count' => (int) ($salesByUser[$id]->sales_count ?? 0),
                'sales_amount' => round((float) ($salesByUser[$id]->sales_amount ?? 0), 2),
                ...$pricing,
                'refunds_count' => (int) ($refunds[$id]->refunds_count ?? 0),
                'refunds_amount' => round((float) ($refunds[$id]->refunds_amount ?? 0), 2),
                'till' => $this->till($tills->get($id, collect())),
            ];
        })->sortByDesc('sales_amount')->values()->all();

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'staff' => $staff,
        ];
    }

    /**
     * Haggling: how much above the list price was agreed, what went below the
     * list price (the floor), and what was given away (at list value).
     *
     * @param  Collection<int, SaleItem>  $items
     * @return array{haggling_uplift: float, above_list_lines: int, below_floor_lines: int, below_floor_amount: float, giveaway_lines: int, giveaway_value: float}
     */
    protected function pricing(Collection $items): array
    {
        $uplift = 0.0;
        $aboveLines = 0;
        $belowLines = 0;
        $belowAmount = 0.0;
        $giveawayLines = 0;
        $giveawayValue = 0.0;

        foreach ($items as $item) {
            $quantity = (float) $item->quantity;
            $list = $item->list_unit_price !== null ? (float) $item->list_unit_price : null;

            if ($item->is_giveaway) {
                $giveawayLines++;
                $giveawayValue += $quantity * ($list ?? 0);

                continue;
            }

            if ($list === null) {
                continue;
            }

            $difference = (float) $item->unit_price - $list;
            if ($difference > 0.004) {
                $aboveLines++;
                $uplift += $difference * $quantity;
            } elseif ($difference < -0.004) {
                $belowLines++;
                $belowAmount += -$difference * $quantity;
            }
        }

        return [
            'haggling_uplift' => round($uplift, 2),
            'above_list_lines' => $aboveLines,
            'below_floor_lines' => $belowLines,
            'below_floor_amount' => round($belowAmount, 2),
            'giveaway_lines' => $giveawayLines,
            'giveaway_value' => round($giveawayValue, 2),
        ];
    }

    /**
     * Their till sessions: how many, how many were counted, and the over/short
     * on the counted ones (only where the float was known).
     *
     * @param  Collection<int, ShiftAssignment>  $sessions
     * @return array{sessions: int, counted: int, over_short: float, short_count: int, history: array<int, array<string, mixed>>}
     */
    protected function till(Collection $sessions): array
    {
        $history = $sessions->map(fn (ShiftAssignment $session) => [
            'id' => $session->id,
            'date' => $session->shift_date->toDateString(),
            'store_id' => $session->store_id,
            'expected_cash' => $session->expected_cash,
            'counted_cash' => $session->closing_cash !== null ? (float) $session->closing_cash : null,
            'over_short' => $session->cash_variance,
            'closed_automatically' => (bool) $session->closed_automatically,
            'open' => $session->status === ShiftStatus::IN_PROGRESS,
        ]);

        $counted = $history->filter(fn (array $row) => $row['over_short'] !== null);

        return [
            'sessions' => $history->count(),
            'counted' => $counted->count(),
            'over_short' => round((float) $counted->sum('over_short'), 2),
            'short_count' => $counted->filter(fn (array $row) => $row['over_short'] < 0)->count(),
            'history' => $history->reverse()->values()->all(),
        ];
    }
}
