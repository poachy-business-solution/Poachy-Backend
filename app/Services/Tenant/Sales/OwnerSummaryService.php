<?php

namespace App\Services\Tenant\Sales;

use App\Enums\Tenant\PaymentMethod;
use App\Enums\Tenant\RefundStatus;
use App\Models\Tenant\Sale;
use App\Models\Tenant\SaleItem;
use App\Models\Tenant\SalePayment;
use App\Models\Tenant\SaleRefund;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The owner's day at a glance, computed live from sales, payments and refunds
 * rather than from the daily aggregates (which count refunded sales as
 * revenue and don't split by payment method).
 *
 * "The day" is the shop's local day: callers pass their UTC offset, since
 * timestamps are stored in UTC and a Nairobi day starts at 21:00 UTC.
 */
class OwnerSummaryService
{
    /**
     * @return array{
     *     date: string,
     *     takings: array{gross_sales: float, refunds: float, net: float, on_credit: float, transactions: int},
     *     by_method: array<int, array{method: string, label: string, received: float, refunded: float, net: float}>,
     *     top_sellers: array<int, array{name: string, quantity: float, revenue: float}>
     * }
     */
    public function forDay(string $date, int $utcOffsetMinutes = 0, ?int $storeId = null, int $topLimit = 5): array
    {
        $start = CarbonImmutable::parse($date, 'UTC')->startOfDay()->subMinutes($utcOffsetMinutes);
        $end = $start->addDay();

        $sales = fn (): Builder => Sale::query()
            ->whereBetween('sale_date', [$start, $end->subSecond()])
            ->when($storeId, fn (Builder $query) => $query->where('store_id', $storeId));

        $grossSales = (float) $sales()->sum('total_amount');
        $onCredit = (float) $sales()->sum('amount_due');
        $transactions = $sales()->count();

        $refunds = SaleRefund::query()
            ->where('status', RefundStatus::COMPLETED)
            ->whereBetween('created_at', [$start, $end->subSecond()])
            ->when($storeId, fn (Builder $query) => $query->where('store_id', $storeId))
            ->get(['refund_amount', 'refund_method']);

        return [
            'date' => $date,
            'takings' => [
                'gross_sales' => round($grossSales, 2),
                'refunds' => round((float) $refunds->sum('refund_amount'), 2),
                'net' => round($grossSales - (float) $refunds->sum('refund_amount'), 2),
                'on_credit' => round($onCredit, 2),
                'transactions' => $transactions,
            ],
            'by_method' => $this->byMethod($sales, $refunds),
            'top_sellers' => $this->topSellers($sales, $topLimit),
        ];
    }

    /**
     * Money actually received per method, net of change handed back (cash
     * only) and of refunds paid out the same way. Credit is not money in.
     *
     * @param  \Closure(): Builder  $sales
     * @param  Collection<int, SaleRefund>  $refunds
     * @return array<int, array{method: string, label: string, received: float, refunded: float, net: float}>
     */
    protected function byMethod(\Closure $sales, $refunds): array
    {
        $saleIds = $sales()->pluck('id');

        $received = SalePayment::query()
            ->whereIn('sale_id', $saleIds)
            ->where('payment_method', '!=', PaymentMethod::CREDIT)
            ->selectRaw('payment_method, SUM(amount) as total')
            ->groupBy('payment_method')
            ->pluck('total', 'payment_method')
            ->map(fn ($total) => (float) $total);

        if ($received->has(PaymentMethod::CASH->value)) {
            $received[PaymentMethod::CASH->value] -= $this->changeGiven($saleIds);
        }

        $refunded = $refunds
            ->groupBy(fn (SaleRefund $refund) => $refund->refund_method->value)
            ->map(fn ($group) => (float) $group->sum('refund_amount'));

        return $received->keys()
            ->merge($refunded->keys()->intersect(array_column(PaymentMethod::cases(), 'value')))
            ->unique()
            ->map(function (string $method) use ($received, $refunded) {
                $in = round($received[$method] ?? 0, 2);
                $out = round($refunded[$method] ?? 0, 2);

                return [
                    'method' => $method,
                    'label' => PaymentMethod::tryFrom($method)?->label() ?? $method,
                    'received' => $in,
                    'refunded' => $out,
                    'net' => round($in - $out, 2),
                ];
            })
            ->sortByDesc('net')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, int>  $saleIds
     */
    protected function changeGiven($saleIds): float
    {
        $cashBySale = SalePayment::query()
            ->whereIn('sale_id', $saleIds)
            ->where('payment_method', PaymentMethod::CASH)
            ->selectRaw('sale_id, SUM(amount) as cash')
            ->groupBy('sale_id')
            ->pluck('cash', 'sale_id');

        return (float) Sale::query()
            ->whereIn('id', $cashBySale->keys())
            ->get(['id', 'amount_paid', 'total_amount'])
            ->sum(fn (Sale $sale) => min(
                max(0, (float) $sale->amount_paid - (float) $sale->total_amount),
                (float) $cashBySale[$sale->id],
            ));
    }

    /**
     * Best sellers by money taken. Giveaways are excluded (they took nothing);
     * unlisted items are grouped by their description.
     *
     * @param  \Closure(): Builder  $sales
     * @return array<int, array{name: string, quantity: float, revenue: float}>
     */
    protected function topSellers(\Closure $sales, int $limit): array
    {
        return SaleItem::query()
            ->whereIn('sale_id', $sales()->select('id'))
            ->where('is_giveaway', false)
            ->with(['product:id,name', 'productVariant', 'bundle'])
            ->get()
            ->groupBy(fn (SaleItem $item) => $item->product_id
                ? 'p'.$item->product_id
                : ($item->bundle_id ? 'b'.$item->bundle_id : 'o'.mb_strtolower(trim((string) $item->description))))
            ->map(fn ($items) => [
                'name' => $items->first()->product?->name ?? $items->first()->display_name,
                'quantity' => round((float) $items->sum('quantity'), 4),
                'revenue' => round((float) $items->sum('subtotal'), 2),
            ])
            ->sortByDesc('revenue')
            ->take($limit)
            ->values()
            ->all();
    }
}
