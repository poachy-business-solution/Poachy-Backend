<?php

namespace App\Services\Central\Marketplace;

use App\Mail\Central\Marketplace\MarketplaceOrderLifecycleMail;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceOrderPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OrderNotificationService
{
    public function notifyCustomer(
        MarketplaceOrder $order,
        string $lifecycleType,
        ?MarketplaceOrderPayment $payment = null,
        array $context = [],
    ): void {
        $send = function () use ($order, $lifecycleType, $payment, $context): void {
            try {
                $freshOrder = MarketplaceOrder::on('central')
                    ->with(['customer.user', 'items', 'payments', 'delivery', 'deliveryAddress'])
                    ->find($order->id);

                if (! $freshOrder?->customer?->user?->email) {
                    Log::info('Skipping marketplace order lifecycle email; customer email missing', [
                        'order_id' => $order->id,
                        'lifecycle_type' => $lifecycleType,
                    ]);

                    return;
                }

                $freshPayment = $payment
                    ? MarketplaceOrderPayment::on('central')->find($payment->id)
                    : null;

                Mail::to($freshOrder->customer->user->email)->queue(
                    new MarketplaceOrderLifecycleMail(
                        order: $freshOrder,
                        customerName: $freshOrder->customer->user->name,
                        lifecycleType: $lifecycleType,
                        payment: $freshPayment,
                        context: $context,
                        orderUrl: $this->orderUrl($freshOrder),
                    )
                );
            } catch (\Throwable $exception) {
                Log::error('Failed to queue marketplace order lifecycle email', [
                    'order_id' => $order->id,
                    'lifecycle_type' => $lifecycleType,
                    'error' => $exception->getMessage(),
                ]);
            }
        };

        if (DB::connection('central')->transactionLevel() > 0) {
            DB::connection('central')->afterCommit($send);

            return;
        }

        $send();
    }

    private function orderUrl(MarketplaceOrder $order): string
    {
        $baseUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');

        return "{$baseUrl}/orders/{$order->order_number}";
    }
}
