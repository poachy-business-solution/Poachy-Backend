<?php

namespace App\Observers\Central;

use App\Enums\Central\OrderStatus;
use App\Jobs\Central\UpdateTenantOrderMetricsJob;
use App\Models\MarketplaceOrder;
use App\Services\Central\Payouts\PayoutLedgerService;
use App\Services\Central\Refunds\RefundService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MarketplaceOrderObserver
{
    public function created(MarketplaceOrder $order): void
    {
        $this->dispatchMetricsJob($order);

        Log::info('MarketplaceOrder created', [
            'order_id' => $order->id,
            'tenant_id' => $order->tenant_id,
            'order_status' => $order->order_status->value,
        ]);
    }

    public function updated(MarketplaceOrder $order): void
    {
        // Only recalculate metrics if order_status changed
        if ($order->wasChanged('order_status')) {
            $this->dispatchMetricsJob($order);

            // Handover (collected or delivered): the shop has earned this order's payout.
            if ($order->order_status === OrderStatus::Completed) {
                app(PayoutLedgerService::class)->recordHandover($order);
            }

            // Cancelled before handover (by the buyer, the shop or a failed hold): money that was
            // taken goes straight back through the gateway, once the cancellation is committed.
            if ($order->order_status === OrderStatus::Cancelled) {
                DB::connection('central')->afterCommit(fn () => app(RefundService::class)->start(
                    $order, (float) $order->total_amount, 'cancellation', $order->cancellation_reason ?: 'Order cancelled',
                ));
            }
        }

        Log::info('MarketplaceOrder updated', [
            'order_id' => $order->id,
            'tenant_id' => $order->tenant_id,
            'changes' => $order->getChanges(),
        ]);
    }

    public function deleted(MarketplaceOrder $order): void
    {
        $this->dispatchMetricsJob($order);

        Log::info('MarketplaceOrder soft-deleted', [
            'order_id' => $order->id,
            'tenant_id' => $order->tenant_id,
            'order_status' => $order->order_status->value,
        ]);
    }

    public function restored(MarketplaceOrder $order): void
    {
        $this->dispatchMetricsJob($order);

        Log::info('MarketplaceOrder restored', [
            'order_id' => $order->id,
            'tenant_id' => $order->tenant_id,
        ]);
    }

    protected function dispatchMetricsJob(MarketplaceOrder $order): void
    {
        UpdateTenantOrderMetricsJob::dispatch($order->tenant_id)
            ->onQueue('sync-normal')
            ->afterCommit();
    }
}
