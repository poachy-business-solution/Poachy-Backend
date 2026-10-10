<?php

namespace App\Services\Tenant\Reports;

use App\Enums\Tenant\ExpenseStatus;
use App\Enums\Tenant\PaymentMethod;
use App\Enums\Tenant\RecurrenceFrequency;
use App\Enums\Tenant\RefundStatus;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Expense;
use App\Models\Tenant\Sale;
use App\Models\Tenant\SaleItem;
use App\Models\Tenant\SaleRefund;
use App\Models\Tenant\SaleRefundItem;
use App\Models\Tenant\Store;
use App\Models\Tenant\Supplier;
use App\Models\Tenant\TradePartner;
use App\Services\Tenant\Sales\OwnerSummaryService;
use App\Services\Tenant\Shift\TillService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The owner's numbers for any day or range, one branch or all: sales, cost
 * of goods, expenses (monthly bills spread across the days), profit, money
 * in by method, who owes whom, comparisons and break-even.
 *
 * Days are the shop's local days (tenant timezone). Profit leaves out VAT
 * collected — it belongs to KRA, not the shop.
 */
class OwnerOverviewService
{
    /** Average days in one recurrence cycle, for spreading bills per day. */
    private const CYCLE_DAYS = [
        'daily' => 1,
        'weekly' => 7,
        'monthly' => 30.4375,
        'quarterly' => 91.3125,
        'yearly' => 365.25,
    ];

    public function __construct(
        protected OwnerSummaryService $summaries,
        protected TillService $tillService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(string $from, string $to, ?int $storeId = null, int $topLimit = 5): array
    {
        $from = CarbonImmutable::parse($from);
        $to = CarbonImmutable::parse($to);
        $days = (int) $from->diffInDays($to) + 1;
        $money = $this->money($from, $to, $storeId);

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'days' => $days],
            'store_id' => $storeId,
            'timezone' => $this->tillService->timezone(),
            'money' => $money,
            'by_method' => $this->summaries->byMethod(
                fn (): Builder => $this->salesQuery($from, $to, $storeId),
                $this->refundsQuery($from, $to, $storeId)->get(['refund_amount', 'refund_method']),
            ),
            'comparisons' => $this->comparisons($from, $to, $days, $storeId, $money),
            'expenses_by_category' => $this->expensesByCategory($from, $to, $storeId),
            'owed' => $this->owed(),
            'break_even' => $this->breakEven($to, $storeId),
            'top_sellers' => $this->summaries->topSellers(fn (): Builder => $this->salesQuery($from, $to, $storeId), $topLimit),
            'branches' => $storeId === null ? $this->branches($from, $to) : [],
        ];
    }

    /**
     * Sales, cost of goods, expenses and profit for a period.
     *
     * items_without_cost counts sold lines whose product has no known cost —
     * profit is overstated until their cost is recorded.
     *
     * @return array{gross_sales: float, refunds: float, sales: float, vat: float, cost_of_goods: float, gross_profit: float, expenses: float, one_off_expenses: float, spread_bills: float, profit: float, transactions: int, on_credit: float, items_without_cost: int}
     */
    public function money(CarbonImmutable $from, CarbonImmutable $to, ?int $storeId): array
    {
        $sales = $this->salesQuery($from, $to, $storeId);
        $grossSales = (float) (clone $sales)->sum('total_amount');
        $vat = (float) (clone $sales)->sum('tax_amount');
        $refunds = (float) $this->refundsQuery($from, $to, $storeId)->sum('refund_amount');

        $costSold = (float) SaleItem::query()
            ->whereIn('sale_id', (clone $sales)->select('id'))
            ->selectRaw('COALESCE(SUM(unit_cost * quantity), 0) as cost')
            ->value('cost');
        $costReturned = (float) SaleRefundItem::query()
            ->join('sale_items', 'sale_items.id', '=', 'sale_refund_items.sale_item_id')
            ->whereIn('sale_refund_items.refund_id', $this->refundsQuery($from, $to, $storeId)->select('id'))
            ->selectRaw('COALESCE(SUM(sale_items.unit_cost * sale_refund_items.quantity_refunded), 0) as cost')
            ->value('cost');

        $itemsWithoutCost = SaleItem::query()
            ->whereIn('sale_id', (clone $sales)->select('id'))
            ->whereNotNull('product_id')
            ->where('is_giveaway', false)
            ->where('unit_cost', '<=', 0)
            ->count();

        $netSales = $grossSales - $refunds;
        $costOfGoods = $costSold - $costReturned;
        $oneOff = $this->oneOffExpenses($from, $to, $storeId)->sum('amount');
        $spread = $this->spreadBills($from, $to, $storeId)->sum('amount');
        $grossProfit = $netSales - $vat - $costOfGoods;

        return [
            'gross_sales' => round($grossSales, 2),
            'refunds' => round($refunds, 2),
            'sales' => round($netSales, 2),
            'vat' => round($vat, 2),
            'cost_of_goods' => round($costOfGoods, 2),
            'gross_profit' => round($grossProfit, 2),
            'expenses' => round($oneOff + $spread, 2),
            'one_off_expenses' => round($oneOff, 2),
            'spread_bills' => round($spread, 2),
            'profit' => round($grossProfit - $oneOff - $spread, 2),
            'transactions' => (clone $sales)->count(),
            'on_credit' => round((float) (clone $sales)->sum('amount_due'), 2),
            'items_without_cost' => $itemsWithoutCost,
        ];
    }

    /**
     * A single day compares with yesterday and the same weekday last week;
     * a range with the period just before it.
     *
     * @param  array<string, mixed>  $current
     * @return array{headline: array<string, mixed>|null, items: array<int, array<string, mixed>>}
     */
    protected function comparisons(CarbonImmutable $from, CarbonImmutable $to, int $days, ?int $storeId, array $current): array
    {
        $periods = $days === 1
            ? [
                ['key' => 'same_weekday_last_week', 'label' => 'last '.$from->subWeek()->format('l'), 'from' => $from->subWeek(), 'to' => $from->subWeek()],
                ['key' => 'yesterday', 'label' => 'yesterday', 'from' => $from->subDay(), 'to' => $from->subDay()],
            ]
            : [
                ['key' => 'previous_period', 'label' => "the {$days} days before", 'from' => $from->subDays($days), 'to' => $from->subDay()],
            ];

        $items = array_map(function (array $period) use ($storeId, $current) {
            $money = $this->money($period['from'], $period['to'], $storeId);

            return [
                'key' => $period['key'],
                'label' => $period['label'],
                'from' => $period['from']->toDateString(),
                'to' => $period['to']->toDateString(),
                'sales' => $money['sales'],
                'profit' => $money['profit'],
                'profit_change' => round($current['profit'] - $money['profit'], 2),
                'profit_change_pct' => $money['profit'] > 0
                    ? round(($current['profit'] - $money['profit']) / $money['profit'] * 100, 1)
                    : null,
                'sales_change_pct' => $money['sales'] > 0
                    ? round(($current['sales'] - $money['sales']) / $money['sales'] * 100, 1)
                    : null,
            ];
        }, $periods);

        return ['headline' => $items[0] ?? null, 'items' => $items];
    }

    /**
     * Where the money went: one-off expenses plus each bill's share of the days.
     *
     * @return array<int, array{category: string, amount: float, share_pct: float, spread: bool}>
     */
    public function expensesByCategory(CarbonImmutable $from, CarbonImmutable $to, ?int $storeId): array
    {
        $rows = $this->oneOffExpenses($from, $to, $storeId)
            ->map(fn (Expense $expense) => ['category' => $expense->category?->name ?? 'Other', 'amount' => (float) $expense->amount, 'spread' => false])
            ->concat($this->spreadBills($from, $to, $storeId)->map(fn (array $bill) => [
                'category' => $bill['category'],
                'amount' => $bill['amount'],
                'spread' => true,
            ]));

        $total = $rows->sum('amount');

        return $rows
            ->groupBy(fn (array $row) => $row['category'].($row['spread'] ? '|spread' : ''))
            ->map(fn (Collection $group) => [
                'category' => $group->first()['category'],
                'amount' => round($group->sum('amount'), 2),
                'share_pct' => $total > 0 ? round($group->sum('amount') / $total * 100, 1) : 0.0,
                'spread' => $group->first()['spread'],
            ])
            ->sortByDesc('amount')
            ->values()
            ->all();
    }

    /**
     * Money owed both ways: customers on credit and neighbour shops owe me;
     * I owe shops and suppliers.
     *
     * @return array{owed_to_you: float, customers: float, shops_owe_you: float, you_owe: float, shops_you_owe: float, suppliers: float}
     */
    public function owed(): array
    {
        $customers = (float) Customer::query()->where('current_debt', '>', 0)->sum('current_debt');
        $balances = TradePartner::query()->with('openLines')->get()->map(fn (TradePartner $partner) => $partner->balance());
        $suppliers = (float) Supplier::query()->where('outstanding_balance', '>', 0)->sum('outstanding_balance');
        $shopsOweYou = (float) $balances->sum('they_owe');
        $shopsYouOwe = (float) $balances->sum('you_owe');

        return [
            'owed_to_you' => round($customers + $shopsOweYou, 2),
            'customers' => round($customers, 2),
            'shops_owe_you' => round($shopsOweYou, 2),
            'you_owe' => round($shopsYouOwe + $suppliers, 2),
            'shops_you_owe' => round($shopsYouOwe, 2),
            'suppliers' => round($suppliers, 2),
        ];
    }

    /**
     * Sales a day needs to cover the shop's costs: average daily costs (bills
     * spread per day + the last 30 days' one-off expenses) divided by the
     * share of each sale left after paying for the goods.
     *
     * @return array{daily_sales_needed: float|null, daily_costs: float, margin_pct: float|null, based_on_days: int}
     */
    public function breakEven(CarbonImmutable $asOf, ?int $storeId): array
    {
        $from = $asOf->subDays(29);
        $money = $this->money($from, $asOf, $storeId);
        $dailyCosts = $money['expenses'] / 30;
        $afterVat = $money['sales'] - $money['vat'];
        $margin = $afterVat > 0 ? $money['gross_profit'] / $afterVat : null;

        return [
            'daily_sales_needed' => $margin !== null && $margin > 0 ? round($dailyCosts / $margin, 2) : null,
            'daily_costs' => round($dailyCosts, 2),
            'margin_pct' => $margin !== null ? round($margin * 100, 1) : null,
            'based_on_days' => 30,
        ];
    }

    /**
     * @return array<int, array{store_id: int, name: string, sales: float, profit: float, transactions: int}>
     */
    public function branches(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $stores = Store::query()->where('is_active', true)->orderBy('id')->get(['id', 'name']);

        if ($stores->count() < 2) {
            return [];
        }

        return $stores->map(function (Store $store) use ($from, $to) {
            $money = $this->money($from, $to, $store->id);

            return [
                'store_id' => $store->id,
                'name' => $store->name,
                'sales' => $money['sales'],
                'profit' => $money['profit'],
                'transactions' => $money['transactions'],
            ];
        })->sortByDesc('sales')->values()->all();
    }

    public function salesQuery(CarbonImmutable $from, CarbonImmutable $to, ?int $storeId): Builder
    {
        [$start, $end] = $this->bounds($from, $to);

        return Sale::query()
            ->whereBetween('sale_date', [$start, $end])
            ->when($storeId, fn (Builder $query) => $query->where('store_id', $storeId));
    }

    public function refundsQuery(CarbonImmutable $from, CarbonImmutable $to, ?int $storeId): Builder
    {
        [$start, $end] = $this->bounds($from, $to);

        return SaleRefund::query()
            ->where('status', RefundStatus::COMPLETED)
            ->whereBetween('created_at', [$start, $end])
            ->when($storeId, fn (Builder $query) => $query->where('store_id', $storeId));
    }

    /**
     * UTC instants of the shop-local days.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function bounds(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $timezone = $this->tillService->timezone();

        return [
            CarbonImmutable::parse($from->toDateString(), $timezone)->startOfDay()->utc(),
            CarbonImmutable::parse($to->toDateString(), $timezone)->endOfDay()->utc(),
        ];
    }

    /**
     * Expenses that aren't recurring bills (bills are spread instead).
     * Company-wide expenses count only in the all-branches view.
     *
     * @return Collection<int, Expense>
     */
    protected function oneOffExpenses(CarbonImmutable $from, CarbonImmutable $to, ?int $storeId): Collection
    {
        return Expense::query()
            ->with('category:id,name')
            ->where('approval_status', '!=', ExpenseStatus::REJECTED)
            ->where('is_recurring', false)
            ->whereNull('parent_expense_id')
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->when($storeId, fn (Builder $query) => $query->where('store_id', $storeId))
            ->get(['id', 'category_id', 'amount']);
    }

    /**
     * Each recurring bill's share of the days in the period (rent of 15,000 a
     * month is about 493 a day).
     *
     * @return Collection<int, array{category: string, amount: float}>
     */
    protected function spreadBills(CarbonImmutable $from, CarbonImmutable $to, ?int $storeId): Collection
    {
        return Expense::query()
            ->with('category:id,name')
            ->where('approval_status', '!=', ExpenseStatus::REJECTED)
            ->where('is_recurring', true)
            ->whereNull('parent_expense_id')
            ->whereDate('recurrence_start_date', '<=', $to->toDateString())
            ->where(fn (Builder $query) => $query->whereNull('recurrence_end_date')->orWhereDate('recurrence_end_date', '>=', $from->toDateString()))
            ->when($storeId, fn (Builder $query) => $query->where('store_id', $storeId))
            ->get()
            ->map(function (Expense $bill) use ($from, $to) {
                $start = CarbonImmutable::parse($bill->recurrence_start_date)->max($from);
                $end = $bill->recurrence_end_date ? CarbonImmutable::parse($bill->recurrence_end_date)->min($to) : $to;
                $daysCovered = max(0, (int) $start->diffInDays($end) + 1);
                $frequency = $bill->recurrence_frequency instanceof RecurrenceFrequency ? $bill->recurrence_frequency->value : 'monthly';
                $cycleDays = self::CYCLE_DAYS[$frequency] * max(1, (int) $bill->recurrence_interval);

                return [
                    'category' => $bill->category?->name ?? 'Bills',
                    'amount' => round((float) $bill->amount / $cycleDays * $daysCovered, 2),
                ];
            });
    }
}
