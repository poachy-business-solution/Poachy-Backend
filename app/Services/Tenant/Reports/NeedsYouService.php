<?php

namespace App\Services\Tenant\Reports;

use App\Enums\Tenant\ExpenseStatus;
use App\Enums\Tenant\ShiftStatus;
use App\Enums\Tenant\TradeDirection;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Expense;
use App\Models\Tenant\Sale;
use App\Models\Tenant\ShiftAssignment;
use App\Models\Tenant\Store;
use App\Models\Tenant\Supplier;
use App\Models\Tenant\TradeLine;
use App\Models\Tenant\TradePartner;
use App\Services\Tenant\Shift\TillService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * What the owner should look at today, in plain words, and the detail of
 * money owed both ways.
 */
class NeedsYouService
{
    public function __construct(
        protected StockReportService $stock,
        protected TillService $tillService,
    ) {}

    /**
     * Items for Home's "Needs you", most urgent first. Each has a type the app
     * routes on, a title and a subtitle.
     *
     * @return array<int, array{type: string, title: string, subtitle: string, count: int, amount: float|null, ids: array<int, int>}>
     */
    public function items(?int $storeId = null): array
    {
        return array_values(array_filter([
            $this->lowStock($storeId),
            $this->tillsShort($storeId),
            $this->expensesToApprove($storeId),
            $this->shopOwing(),
            $this->customersOwing(),
        ]));
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function lowStock(?int $storeId): ?array
    {
        $storeIds = $storeId !== null
            ? [$storeId]
            : Store::query()->where('is_active', true)->pluck('id')->map(fn ($id) => (int) $id)->all();

        $low = collect($storeIds)
            ->flatMap(fn (int $id) => collect($this->stock->stockHealth($id)['items'])->whereIn('status', ['out', 'low']))
            ->unique('product_id')
            ->values();

        if ($low->isEmpty()) {
            return null;
        }

        return [
            'type' => 'low_stock',
            'title' => $low->count().' '.($low->count() === 1 ? 'item' : 'items').' running low',
            'subtitle' => $low->take(3)->pluck('name')->join(', ').($low->count() > 3 ? '…' : ''),
            'count' => $low->count(),
            'amount' => null,
            'ids' => $low->pluck('product_id')->all(),
        ];
    }

    /**
     * Counted tills that came up short in the last two days.
     *
     * @return array<string, mixed>|null
     */
    protected function tillsShort(?int $storeId): ?array
    {
        $threshold = (float) config('shift.cash_variance_threshold', 100);
        $since = CarbonImmutable::parse($this->tillService->localToday())->subDay()->toDateString();

        $short = ShiftAssignment::query()
            ->with('user:id,name')
            ->where('status', ShiftStatus::COMPLETED)
            ->whereNotNull('closing_cash')
            ->whereDate('shift_date', '>=', $since)
            ->when($storeId, fn ($query) => $query->where('store_id', $storeId))
            ->get()
            ->filter(fn (ShiftAssignment $session) => ($session->cash_variance ?? 0) <= -$threshold)
            ->values();

        if ($short->isEmpty()) {
            return null;
        }

        $total = round(abs((float) $short->sum('cash_variance')), 2);
        $first = $short->first();

        return [
            'type' => 'till_short',
            'title' => $short->count() === 1
                ? 'Till short by KSh '.number_format($total)
                : "{$short->count()} tills short by KSh ".number_format($total),
            'subtitle' => $short->map(fn (ShiftAssignment $s) => ($s->user?->name ?? 'Someone').' · '.$s->shift_date->format('j M'))->take(2)->join(' · ')
                .($first->cash_variance_reason ? ' — "'.$first->cash_variance_reason.'"' : ''),
            'count' => $short->count(),
            'amount' => $total,
            'ids' => $short->pluck('id')->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function expensesToApprove(?int $storeId): ?array
    {
        $pending = Expense::query()
            ->with(['category:id,name', 'creator:id,name'])
            ->where('approval_status', ExpenseStatus::PENDING)
            ->when($storeId, fn ($query) => $query->where('store_id', $storeId))
            ->latest()
            ->get();

        if ($pending->isEmpty()) {
            return null;
        }

        $people = $pending->pluck('creator.name')->filter()->unique()->take(2)->join(', ');

        return [
            'type' => 'expenses_to_approve',
            'title' => $pending->count().' '.($pending->count() === 1 ? 'expense' : 'expenses').' to approve',
            'subtitle' => $pending->take(2)->map(fn (Expense $e) => ($e->category?->name ?? 'Expense').' '.number_format((float) $e->amount))->join(' · ')
                .($people ? " · by {$people}" : ''),
            'count' => $pending->count(),
            'amount' => round((float) $pending->sum('amount'), 2),
            'ids' => $pending->pluck('id')->all(),
        ];
    }

    /**
     * The neighbour shop that has owed the longest.
     *
     * @return array<string, mixed>|null
     */
    protected function shopOwing(): ?array
    {
        $line = TradeLine::query()
            ->with(['partner', 'product:id,name'])
            ->where('direction', TradeDirection::LENT)
            ->where('status', TradeLine::STATUS_OPEN)
            ->orderBy('opened_at')
            ->first();

        if ($line === null || $line->partner === null) {
            return null;
        }

        $owed = $line->partner->balance()['they_owe'];
        $days = (int) $line->opened_at->diffInDays(now());

        return [
            'type' => 'shop_owes',
            'title' => "{$line->partner->name} owes KSh ".number_format($owed),
            'subtitle' => rtrim(rtrim(number_format($line->outstandingQuantity(), 2, '.', ''), '0'), '.').' × '.($line->product?->name ?? 'goods')
                .' not back · '.$days.' '.($days === 1 ? 'day' : 'days'),
            'count' => 1,
            'amount' => $owed,
            'ids' => [$line->partner->id],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function customersOwing(): ?array
    {
        $customers = Customer::query()->where('current_debt', '>', 0)->orderByDesc('current_debt')->get(['id', 'name', 'current_debt']);

        if ($customers->isEmpty()) {
            return null;
        }

        return [
            'type' => 'customers_owe',
            'title' => $customers->count().' '.($customers->count() === 1 ? 'customer owes' : 'customers owe').' KSh '.number_format((float) $customers->sum('current_debt')),
            'subtitle' => $customers->take(2)->map(fn (Customer $c) => ($c->name ?: 'Customer').' '.number_format((float) $c->current_debt))->join(' · '),
            'count' => $customers->count(),
            'amount' => round((float) $customers->sum('current_debt'), 2),
            'ids' => $customers->pluck('id')->all(),
        ];
    }

    /**
     * Everyone who owes the shop and everyone it owes, oldest first.
     *
     * @return array{owed_to_you: array{customers: array<int, array<string, mixed>>, shops: array<int, array<string, mixed>>, total: float}, you_owe: array{shops: array<int, array<string, mixed>>, suppliers: array<int, array<string, mixed>>, total: float}}
     */
    public function owedBothWays(): array
    {
        $oldestCreditSale = Sale::query()
            ->where('amount_due', '>', 0)
            ->whereNotNull('customer_id')
            ->groupBy('customer_id')
            ->selectRaw('customer_id, MIN(sale_date) as oldest')
            ->pluck('oldest', 'customer_id');

        $customers = Customer::query()
            ->where('current_debt', '>', 0)
            ->get(['id', 'name', 'phone', 'current_debt'])
            ->map(fn (Customer $c) => [
                'customer_id' => $c->id,
                'name' => $c->name ?: 'Customer',
                'phone' => $c->phone,
                'amount' => round((float) $c->current_debt, 2),
                'since' => isset($oldestCreditSale[$c->id]) ? CarbonImmutable::parse($oldestCreditSale[$c->id])->toISOString() : null,
            ])
            ->sortBy(fn (array $row) => $row['since'] ?? '9999')
            ->values();

        $partners = TradePartner::query()->with('openLines.product')->get();
        $shopsOweYou = $this->partnerRows($partners, 'they_owe');
        $shopsYouOwe = $this->partnerRows($partners, 'you_owe');

        $suppliers = Supplier::query()
            ->where('outstanding_balance', '>', 0)
            ->orderByDesc('outstanding_balance')
            ->get(['id', 'name', 'outstanding_balance'])
            ->map(fn (Supplier $s) => ['supplier_id' => $s->id, 'name' => $s->name, 'amount' => round((float) $s->outstanding_balance, 2)])
            ->values();

        return [
            'owed_to_you' => [
                'customers' => $customers->all(),
                'shops' => $shopsOweYou->all(),
                'total' => round($customers->sum('amount') + $shopsOweYou->sum('amount'), 2),
            ],
            'you_owe' => [
                'shops' => $shopsYouOwe->all(),
                'suppliers' => $suppliers->all(),
                'total' => round($shopsYouOwe->sum('amount') + $suppliers->sum('amount'), 2),
            ],
        ];
    }

    /**
     * @param  Collection<int, TradePartner>  $partners
     * @return Collection<int, array<string, mixed>>
     */
    protected function partnerRows(Collection $partners, string $side): Collection
    {
        return $partners
            ->map(fn (TradePartner $p) => ['partner' => $p, 'balance' => $p->balance()])
            ->filter(fn (array $row) => $row['balance'][$side] > 0)
            ->map(fn (array $row) => [
                'trade_partner_id' => $row['partner']->id,
                'name' => $row['partner']->name,
                'amount' => $row['balance'][$side],
                'since' => $row['partner']->openLines
                    ->where('direction', $side === 'they_owe' ? TradeDirection::LENT : TradeDirection::BORROWED)
                    ->min('opened_at')?->toISOString(),
            ])
            ->sortBy(fn (array $row) => $row['since'] ?? '9999')
            ->values();
    }
}
