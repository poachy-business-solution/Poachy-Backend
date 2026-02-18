<?php

namespace App\Jobs\Central;

use App\Models\MarketplaceOrder;
use App\Services\Central\Marketplace\OutboundSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPaymentConfirmation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;

    public int $tries = 10;

    public int $maxExceptions = 10;

    /** @var array<int, int> */
    public array $backoff = [60, 120, 300, 600];

    public function __construct(
        public int $orderId,
    ) {}

    public function handle(OutboundSyncService $syncService): void
    {
        $order = MarketplaceOrder::on('central')
            ->with('payments')
            ->find($this->orderId);

        if (! $order) {
            Log::error('Order not found for payment confirmation sync', [
                'order_id' => $this->orderId,
            ]);

            return;
        }

        $completedPayment = $order->payments()
            ->where('payment_status', 'completed')
            ->latest()
            ->first();

        if (! $completedPayment) {
            Log::info('No completed payment found, skipping', [
                'order_id' => $this->orderId,
            ]);

            return;
        }

        $syncService->queuePaymentSync($completedPayment);

        Log::info('Payment confirmation sync queued', [
            'order_id'   => $order->id,
            'payment_id' => $completedPayment->id,
            'tenant_id'  => $order->tenant_id,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessPaymentConfirmation job failed', [
            'order_id' => $this->orderId,
            'error'    => $exception->getMessage(),
        ]);
    }
}
