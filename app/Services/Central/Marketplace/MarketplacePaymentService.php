<?php

namespace App\Services\Central\Marketplace;

use App\Enums\Central\MarketplacePaymentMethod;
use App\Enums\Central\MarketplacePaymentStatus;
use App\Enums\Central\OrderStatus;
use App\Enums\Central\ReservationStatus;
use App\Events\Central\Marketplace\PaymentAttempted;
use App\Events\Central\Marketplace\PaymentCompleted;
use App\Events\Central\Marketplace\PaymentFailed;
use App\Exceptions\MpesaException;
use App\Jobs\Central\ProcessOrderCancellation;
use App\Jobs\Central\ProcessPaymentConfirmation;
use App\Models\CentralPaymentLog;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceOrderPayment;
use App\Services\Shared\Mpesa\MpesaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MarketplacePaymentService
{
    public function __construct(
        private readonly MpesaService $mpesaService,
        private readonly MarketplaceProductService $productService,
    ) {}

    // =========================================================================

    /**
     * Initiate payment for an order.
     * Routes to the appropriate handler based on the payment method captured at checkout.
     *
     * @param array{phone_number?: string} $paymentData
     *
     * @return array{payment: MarketplaceOrderPayment, message: string, instructions?: array<string, mixed>}
     *
     * @throws \RuntimeException
     */
    public function initiatePayment(MarketplaceOrder $order, array $paymentData): array
    {
        if (! $order->canAcceptPayment()) {
            throw new \RuntimeException(
                'Payment cannot be initiated. Reservation must be confirmed and payment deadline must not have passed.'
            );
        }

        $payment = $order->payments()
            ->whereIn('payment_status', [
                MarketplacePaymentStatus::Pending->value,
                MarketplacePaymentStatus::Processing->value,
            ])
            ->latest()
            ->first();

        if (! $payment) {
            throw new \RuntimeException('No pending payment record found for this order.');
        }

        return match ($payment->payment_method) {
            MarketplacePaymentMethod::Mpesa          => $this->initiateMpesaSTKPayment($order, $payment, $paymentData),
            MarketplacePaymentMethod::MpesaPaybill   => $this->initiateMpesaPaybillPayment($order, $payment),
            MarketplacePaymentMethod::CashOnDelivery => $this->initiateCashOnDeliveryPayment($order, $payment),
            default                                  => throw new \RuntimeException(
                "Payment method '{$payment->payment_method->label()}' is not yet available. Please choose M-Pesa STK, M-Pesa Paybill, or Cash on Delivery."
            ),
        };
    }

    /**
     * Initiate an M-Pesa STK push for the order.
     *
     * If the payment is already Processing and was initiated <60 seconds ago, returns the
     * current status with a "please wait" message instead of re-initiating.
     *
     * @param array{phone_number?: string} $paymentData
     *
     * @return array{payment: MarketplaceOrderPayment, message: string, instructions: array<string, mixed>}
     *
     * @throws \RuntimeException
     */
    private function initiateMpesaSTKPayment(
        MarketplaceOrder $order,
        MarketplaceOrderPayment $payment,
        array $paymentData,
    ): array {
        if (! isset($paymentData['phone_number'])) {
            throw new \RuntimeException('A phone number is required for M-Pesa payment.');
        }

        $phoneNumber = $paymentData['phone_number'];

        // If already Processing and recently initiated, tell the client to wait
        if (
            $payment->payment_status === MarketplacePaymentStatus::Processing
            && $payment->initiated_at
            && $payment->initiated_at->diffInSeconds(now()) < 60
        ) {
            return [
                'payment'      => $payment,
                'message'      => 'STK push already sent. Please check your phone.',
                'instructions' => ['wait_seconds' => 60 - $payment->initiated_at->diffInSeconds(now())],
            ];
        }

        try {
            $stkResult = $this->mpesaService->initiateSTKPush(
                phoneNumber:      $phoneNumber,
                amount:           (float) $payment->amount,
                accountReference: $order->order_number,
                transactionDesc:  'Marketplace order payment',
                callbackUrl:      config('mpesa.stk_callback_url'),
            );
        } catch (MpesaException $e) {
            Log::channel('mpesa')->error('Marketplace STK push failed', [
                'order_id'   => $order->id,
                'payment_id' => $payment->id,
                'error'      => $e->getMessage(),
            ]);
            throw $e;
        }

        $payment->update([
            'payment_status'        => MarketplacePaymentStatus::Processing,
            'transaction_reference' => $stkResult['checkout_request_id'],
            'payment_metadata'      => array_merge($payment->payment_metadata ?? [], [
                'merchant_request_id' => $stkResult['merchant_request_id'],
                'phone_number'        => $phoneNumber,
                'stk_initiated_at'    => now()->toIso8601String(),
            ]),
            'initiated_at' => now(),
        ]);

        CentralPaymentLog::record('marketplace_order_payment', $payment->id, 'stk_initiated', [
            'customer_id'          => $order->customer_id,
            'amount'               => (float) $payment->amount,
            'customer_phone'       => $phoneNumber,
            'transaction_reference' => $stkResult['checkout_request_id'],
        ]);

        Log::channel('mpesa')->info('Marketplace STK push initiated', [
            'order_id'            => $order->id,
            'payment_id'          => $payment->id,
            'checkout_request_id' => $stkResult['checkout_request_id'],
        ]);

        event(new PaymentAttempted(
            order: $order,
            payment: $payment->fresh(),
            paymentMethod: MarketplacePaymentMethod::Mpesa->value,
        ));

        return [
            'payment'      => $payment->fresh(),
            'message'      => 'STK push sent. Please complete payment on your phone.',
            'instructions' => [
                'checkout_request_id' => $stkResult['checkout_request_id'],
                'phone_number'        => $phoneNumber,
            ],
        ];
    }

    /**
     * Initiate a Paybill payment for a marketplace order.
     * No API call needed — returns instructions for the customer to pay via M-Pesa menu.
     *
     * @return array{payment: MarketplaceOrderPayment, message: string, instructions: array<string, mixed>}
     */
    private function initiateMpesaPaybillPayment(
        MarketplaceOrder $order,
        MarketplaceOrderPayment $payment,
    ): array {
        $credentials = $this->mpesaService->getActiveCredentials();

        CentralPaymentLog::record('marketplace_order_payment', $payment->id, 'paybill_instructions_issued', [
            'customer_id' => $order->customer_id,
            'amount'      => (float) $payment->amount,
        ]);

        event(new PaymentAttempted(
            order: $order,
            payment: $payment,
            paymentMethod: MarketplacePaymentMethod::MpesaPaybill->value,
        ));

        return [
            'payment' => $payment,
            'message' => 'Pay via M-Pesa Paybill. Use the details below.',
            'instructions' => [
                'business_number' => $credentials['shortcode'],
                'account_number'  => $order->order_number,
                'amount'          => (float) $payment->amount,
                'expires_at'      => $order->payment_deadline_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * Process a C2B Paybill confirmation for a marketplace order.
     * Called by ProcessMpesaC2BConfirmationJob when Safaricom confirms payment.
     *
     * @param  array<string, mixed>  $parsedPayload  Output of MpesaService::parseC2BPayload()
     */
    public function processC2BConfirmation(array $parsedPayload): MarketplaceOrderPayment
    {
        $orderNumber   = $parsedPayload['bill_ref_number'];
        $transactionId = $parsedPayload['transaction_id'];

        // Idempotency: TransID already completed
        $existing = MarketplaceOrderPayment::on('central')
            ->where('provider_reference', $transactionId)
            ->where('payment_status', MarketplacePaymentStatus::Completed)
            ->first();

        if ($existing) {
            Log::channel('mpesa')->info('C2B marketplace confirmation — duplicate ignored', [
                'transaction_id' => $transactionId,
                'payment_id'     => $existing->id,
            ]);

            return $existing;
        }

        $order = MarketplaceOrder::on('central')
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        $payment = $order->payments()
            ->whereIn('payment_status', [
                MarketplacePaymentStatus::Pending->value,
                MarketplacePaymentStatus::Processing->value,
            ])
            ->latest()
            ->firstOrFail();

        CentralPaymentLog::record('marketplace_order_payment', $payment->id, 'c2b_confirmation_received', [
            'customer_id'          => $order->customer_id,
            'amount'               => $parsedPayload['amount'],
            'customer_phone'       => $parsedPayload['phone'],
            'transaction_reference' => $transactionId,
            'raw_payload'          => $parsedPayload,
        ]);

        return DB::connection('central')->transaction(function () use ($order, $payment, $transactionId, $parsedPayload) {
            $order = MarketplaceOrder::on('central')->lockForUpdate()->find($order->id);

            if ($order->order_status->isTerminal()) {
                $this->initiateRefund($payment, (float) $payment->amount);

                return $payment->fresh();
            }

            $this->confirmPayment($payment, $transactionId, $transactionId);

            return $payment->fresh();
        });
    }

    /**
     * Process an STK Push callback from Safaricom for a marketplace order.
     * This replaces the old handleMpesaCallback (which remains for backward compat).
     *
     * @param  array{transaction_reference: string|null, status: string, provider_reference: string|null, failure_reason: string|null, failure_code: string|null}  $parsedPayload
     */
    public function processSTKCallback(array $parsedPayload): MarketplaceOrderPayment
    {
        return $this->processPaymentWebhook($parsedPayload);
    }

    /**
     * Confirm a Cash on Delivery order.
     * Marks the payment as Completed immediately (customer commitment to pay on delivery).
     * The tenant will create a Sale with PENDING payment status for cash collection.
     *
     * @return array{payment: MarketplaceOrderPayment, message: string}
     */
    private function initiateCashOnDeliveryPayment(
        MarketplaceOrder $order,
        MarketplaceOrderPayment $payment,
    ): array {
        $codReference = 'COD-' . $order->order_number . '-' . now()->timestamp;

        // Fire analytics event for payment attempt (COD)
        event(new PaymentAttempted(
            order: $order,
            payment: $payment,
            paymentMethod: MarketplacePaymentMethod::CashOnDelivery->value,
        ));

        $this->confirmPayment($payment, $codReference);

        Log::info('Cash on delivery confirmed', [
            'order_id'      => $order->id,
            'payment_id'    => $payment->id,
            'cod_reference' => $codReference,
        ]);

        return [
            'payment' => $payment->fresh(),
            'message' => 'Cash on delivery confirmed. Payment will be collected upon delivery.',
        ];
    }

    /**
     * Handle an incoming M-Pesa STK callback.
     * Validates the callback, parses the payload, then routes to processPaymentWebhook().
     *
     * @throws \RuntimeException if callback validation fails.
     */
    public function handleMpesaCallback(array $callbackPayload): MarketplaceOrderPayment
    {
        $webhookData = $this->mpesaService->parseSTKCallbackPayload($callbackPayload);

        return $this->processPaymentWebhook($webhookData);
    }

    /**
     * Process incoming payment webhook.
     * Idempotent: skips if transaction_reference already completed.
     * Race-safe: locks order row and checks cancellation before confirming.
     */
    public function processPaymentWebhook(array $webhookData): MarketplaceOrderPayment
    {
        $transactionReference = $webhookData['transaction_reference'];

        // Idempotency: if already completed, return existing
        $existingPayment = MarketplaceOrderPayment::on('central')
            ->where('transaction_reference', $transactionReference)
            ->where('payment_status', MarketplacePaymentStatus::Completed)
            ->first();

        if ($existingPayment) {
            Log::info('Duplicate payment webhook ignored', [
                'transaction_reference' => $transactionReference,
                'payment_id'           => $existingPayment->id,
            ]);

            return $existingPayment;
        }

        $payment = MarketplaceOrderPayment::on('central')
            ->where('transaction_reference', $transactionReference)
            ->firstOrFail();

        $isSuccess = ($webhookData['status'] ?? '') === 'success';

        if (! $isSuccess) {
            $this->handlePaymentFailure(
                $payment,
                $webhookData['failure_reason'] ?? 'Payment failed',
                $webhookData['failure_code'] ?? null
            );

            return $payment->fresh();
        }

        // Race condition guard: lock order row, check if already cancelled
        return DB::connection('central')->transaction(function () use ($payment, $webhookData) {
            $order = MarketplaceOrder::on('central')
                ->lockForUpdate()
                ->findOrFail($payment->order_id);

            if ($order->order_status->isTerminal()) {
                Log::info('Payment arrived for cancelled/terminal order — initiating refund', [
                    'order_id'     => $order->id,
                    'order_status' => $order->order_status->value,
                    'payment_id'   => $payment->id,
                ]);

                $this->initiateRefund($payment, (float) $payment->amount);

                return $payment->fresh();
            }

            $this->confirmPayment(
                $payment,
                $webhookData['transaction_reference'] ?? null,
                $webhookData['provider_reference'] ?? null
            );

            return $payment->fresh();
        });
    }

    /**
     * Confirm a payment and update order status.
     */
    public function confirmPayment(
        MarketplaceOrderPayment $payment,
        ?string $transactionReference = null,
        ?string $providerReference = null,
    ): void {
        $payment->markAsCompleted($transactionReference, $providerReference);

        $order = $payment->order;
        $order->update(['order_status' => OrderStatus::Confirmed]);

        ProcessPaymentConfirmation::dispatch($order->id);

        // Increment order counts asynchronously — non-blocking, best-effort
        $productIds = $order->items()->pluck('marketplace_product_id')->all();

        dispatch(function () use ($productIds) {
            foreach ($productIds as $productId) {
                $this->productService->incrementOrderCount($productId);
            }
        })->afterResponse();

        Log::info('Payment confirmed', [
            'order_id'   => $order->id,
            'payment_id' => $payment->id,
            'amount'     => $payment->amount,
            'method'     => $payment->payment_method->value,
        ]);

        // Fire analytics event for payment completion
        event(new PaymentCompleted(
            order: $order,
            payment: $payment->fresh(),
        ));
    }

    /**
     * Handle a failed payment.
     */
    public function handlePaymentFailure(
        MarketplaceOrderPayment $payment,
        string $reason,
        ?string $code = null,
    ): void {
        $payment->markAsFailed($reason, $code);

        Log::info('Payment failed', [
            'order_id'   => $payment->order_id,
            'payment_id' => $payment->id,
            'reason'     => $reason,
        ]);

        // Fire analytics event for payment failure
        event(new PaymentFailed(
            order: $payment->order,
            payment: $payment->fresh(),
            failureReason: $reason,
        ));
    }

    /**
     * Handle payment timeout (called by MonitorPaymentDeadlines job).
     * Cancels order and dispatches reservation release.
     */
    public function handlePaymentTimeout(MarketplaceOrder $order): void
    {
        if ($order->order_status->isTerminal()) {
            return;
        }

        $hasPaidPayment = $order->payments()
            ->where('payment_status', MarketplacePaymentStatus::Completed)
            ->exists();

        if ($hasPaidPayment) {
            return;
        }

        DB::connection('central')->transaction(function () use ($order) {
            $order->payments()
                ->where('payment_status', MarketplacePaymentStatus::Pending)
                ->orWhere('payment_status', MarketplacePaymentStatus::Processing)
                ->update([
                    'payment_status' => MarketplacePaymentStatus::Failed,
                    'failed_at'      => now(),
                    'failure_reason'  => 'Payment deadline exceeded',
                ]);

            $order->update([
                'reservation_status' => ReservationStatus::Released,
            ]);

            $order->cancel('Payment deadline exceeded — reservation released.');
        });

        ProcessOrderCancellation::dispatch($order->id);

        Log::info('Payment deadline exceeded — order cancelled', [
            'order_id' => $order->id,
        ]);
    }

    /**
     * Initiate a refund for a payment.
     */
    public function initiateRefund(MarketplaceOrderPayment $payment, float $amount): void
    {
        $payment->markAsRefunded($amount);

        Log::info('Refund initiated', [
            'order_id'        => $payment->order_id,
            'payment_id'      => $payment->id,
            'refunded_amount' => $amount,
        ]);
    }

    /**
     * Get the current payment status for an order.
     */
    public function getPaymentStatus(MarketplaceOrder $order): ?MarketplaceOrderPayment
    {
        return $order->payments()->latest()->first();
    }
}
