<?php

namespace App\Services\Tenant\Marketplace;

use App\Enums\Tenant\MarketplaceFulfillmentStatus;
use App\Enums\Tenant\PaymentStatus;
use App\Models\MarketplaceReservation;
use App\Models\Tenant\MarketplaceSale;
use App\Services\Central\Payouts\PayoutStatementService;
use App\Services\Tenant\Shift\TillService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The app's Marketplace tab: what the shop sold online, what is waiting to be handed over,
 * reservations on their way, earnings, and the alerts that need someone now.
 */
class MarketplaceSummaryService
{
    public function __construct(protected TillService $tillService) {}

    /**
     * @return array{
     *     orders: array{to_prepare: int, preparing: int, ready: int, overdue: int, delivered_7d: int},
     *     sales: array{today: array{count: int, amount: float}, last_7_days: array{count: int, amount: float}, last_30_days: array{count: int, amount: float}},
     *     reservations: array{open: int, expiring_today: int},
     *     earnings: array{gross_30d: float, fees_30d: float, commission_30d: float, clawbacks_30d: float, net_30d: float, paid_out_30d: float, held: float, available: float, payouts_enabled: bool},
     *     needs_you: array<int, array{type: string, title: string, subtitle: string, count: int, amount: float|null, ids: array<int, int>}>
     * }
     */
    public function summary(?int $storeId = null): array
    {
        $timezone = $this->tillService->timezone();
        $todayStart = CarbonImmutable::now($timezone)->startOfDay()->utc();
        $sales = fn (): Builder => MarketplaceSale::query()->when($storeId, fn (Builder $q) => $q->where('store_id', $storeId));
        $status = fn (MarketplaceFulfillmentStatus ...$statuses): int => $sales()->whereIn('fulfillment_status', $statuses)->count();

        $paidSince = function (CarbonImmutable $since) use ($sales): array {
            $query = $sales()->where('payment_status', PaymentStatus::PAID)
                ->where('fulfillment_status', '!=', MarketplaceFulfillmentStatus::CANCELLED)
                ->where('sale_date', '>=', $since);

            return ['count' => (clone $query)->count(), 'amount' => round((float) $query->sum('total_amount'), 2)];
        };

        $last30 = $paidSince($todayStart->subDays(29));
        $tenantId = (string) tenant()->id;
        $openReservations = MarketplaceReservation::on('central')->forTenant($tenantId)->open()->where('expires_at', '>', now());

        return [
            'orders' => [
                'to_prepare' => $status(MarketplaceFulfillmentStatus::PENDING, MarketplaceFulfillmentStatus::CONFIRMED),
                'preparing' => $status(MarketplaceFulfillmentStatus::PREPARING),
                'ready' => $status(MarketplaceFulfillmentStatus::READY),
                'overdue' => $this->overdue($storeId)->count(),
                'delivered_7d' => $sales()->where('fulfillment_status', MarketplaceFulfillmentStatus::DELIVERED)
                    ->where('updated_at', '>=', $todayStart->subDays(6))->count(),
            ],
            'sales' => [
                'today' => $paidSince($todayStart),
                'last_7_days' => $paidSince($todayStart->subDays(6)),
                'last_30_days' => $last30,
            ],
            'reservations' => [
                'open' => (clone $openReservations)->count(),
                'expiring_today' => (clone $openReservations)->where('expires_at', '<', $todayStart->addDay())->count(),
            ],
            // From the payout ledger: what was handed over, fees, clawbacks, paid out, still to come.
            'earnings' => app(PayoutStatementService::class)->earnings($tenantId),
            'needs_you' => $this->needsYou($storeId),
        ];
    }

    /**
     * Marketplace items for Home's "Needs you" and the tab's alerts, most urgent first.
     *
     * @return array<int, array{type: string, title: string, subtitle: string, count: int, amount: float|null, ids: array<int, int>}>
     */
    public function needsYou(?int $storeId = null): array
    {
        return array_values(array_filter([
            $this->overdueItem($storeId),
            $this->newOrdersItem($storeId),
            $this->reservationsItem(),
        ]));
    }

    protected function overdue(?int $storeId): Builder
    {
        return MarketplaceSale::query()
            ->when($storeId, fn (Builder $q) => $q->where('store_id', $storeId))
            ->whereNotIn('fulfillment_status', [MarketplaceFulfillmentStatus::DELIVERED, MarketplaceFulfillmentStatus::CANCELLED])
            ->where('sale_date', '<', now()->subHours(MarketplaceSale::HANDOVER_DUE_HOURS));
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function overdueItem(?int $storeId): ?array
    {
        $late = $this->overdue($storeId)->orderBy('sale_date')->get(['id', 'sale_number', 'fulfillment_type']);
        if ($late->isEmpty()) {
            return null;
        }

        return [
            'type' => 'online_overdue',
            'title' => $late->count().' online '.($late->count() === 1 ? 'order' : 'orders').' not handed over',
            'subtitle' => 'Paid more than '.MarketplaceSale::HANDOVER_DUE_HOURS.' hours ago · '.$late->take(2)->pluck('sale_number')->join(', '),
            'count' => $late->count(),
            'amount' => null,
            'ids' => $late->pluck('id')->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function newOrdersItem(?int $storeId): ?array
    {
        $new = MarketplaceSale::query()
            ->when($storeId, fn (Builder $q) => $q->where('store_id', $storeId))
            ->whereIn('fulfillment_status', [MarketplaceFulfillmentStatus::PENDING, MarketplaceFulfillmentStatus::CONFIRMED])
            ->where('sale_date', '>=', now()->subHours(MarketplaceSale::HANDOVER_DUE_HOURS))
            ->get(['id', 'total_amount', 'fulfillment_type']);
        if ($new->isEmpty()) {
            return null;
        }
        $deliveries = $new->where('fulfillment_type', 'delivery')->count();

        return [
            'type' => 'online_orders',
            'title' => $new->count().' new online '.($new->count() === 1 ? 'order' : 'orders').' to prepare',
            'subtitle' => 'Paid · KSh '.number_format((float) $new->sum('total_amount')).($deliveries ? " · {$deliveries} to deliver" : ''),
            'count' => $new->count(),
            'amount' => round((float) $new->sum('total_amount'), 2),
            'ids' => $new->pluck('id')->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function reservationsItem(): ?array
    {
        $open = MarketplaceReservation::on('central')->forTenant((string) tenant()->id)->open()
            ->where('expires_at', '>', now())->orderBy('expires_at')->get(['id', 'code', 'product_name', 'buyer_name']);
        if ($open->isEmpty()) {
            return null;
        }

        return [
            'type' => 'reservations_arriving',
            'title' => $open->count().' '.($open->count() === 1 ? 'reservation' : 'reservations').' to collect',
            'subtitle' => $open->take(2)->map(fn ($r) => "{$r->code} · {$r->product_name}")->join(' · '),
            'count' => $open->count(),
            'amount' => null,
            'ids' => $open->pluck('id')->all(),
        ];
    }
}
