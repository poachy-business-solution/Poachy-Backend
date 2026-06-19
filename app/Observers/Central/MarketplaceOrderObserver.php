<?php

namespace App\Observers\Central;

use App\Jobs\Central\UpdateTenantOrderMetricsJob;
use App\Models\MarketplaceOrder;
use Illuminate\Support\Facades\Log;

class MarketplaceOrderObserver
{
    public function created(MarketplaceOrder $order): void
    {
        $this->dispatchMetricsJob($order);

        Log::info('MarketplaceOrder created', [
            'order_id'     => $order->id,
            'tenant_id'    => $order->tenant_id,
            'order_status' => $order->order_status->value,
        ]);
    }

    public function updated(MarketplaceOrder $order): void
    {
        // Only recalculate metrics if order_status changed
        if ($order->wasChanged('order_status')) {
            $this->dispatchMetricsJob($order);
        }

        Log::info('MarketplaceOrder updated', [
            'order_id'  => $order->id,
            'tenant_id' => $order->tenant_id,
            'changes'   => $order->getChanges(),
        ]);
    }

    public function deleted(MarketplaceOrder $order): void
    {
        $this->dispatchMetricsJob($order);

        Log::info('MarketplaceOrder soft-deleted', [
            'order_id'     => $order->id,
            'tenant_id'    => $order->tenant_id,
            'order_status' => $order->order_status->value,
        ]);
    }

    public function restored(MarketplaceOrder $order): void
    {
        $this->dispatchMetricsJob($order);

        Log::info('MarketplaceOrder restored', [
            'order_id'  => $order->id,
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
