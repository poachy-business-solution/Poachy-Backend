<?php

namespace App\Services\Central\Marketplace;

use App\Enums\Central\MarketplacePaymentMethod;
use App\Enums\Central\MarketplacePaymentStatus;
use App\Enums\Central\OrderStatus;
use App\Enums\Central\ReservationStatus;
use App\Events\Central\Marketplace\PaymentAttempted;
use App\Events\Central\Marketplace\PaymentCompleted;
use App\Events\Central\Marketplace\PaymentFailed;
use App\Exceptions\CardPaymentException;
use App\Exceptions\InvalidWebhookSignatureException;
use App\Exceptions\MpesaException;
use App\Jobs\Central\ProcessOrderCancellation;
use App\Jobs\Central\ProcessPaymentConfirmation;
use App\Models\CentralPaymentLog;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceOrderPayment;
use App\Models\UnmatchedMpesaPayment;
use App\Helpers\PhoneNumber;
use App\Services\Central\Refunds\RefundService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Services\Shared\Card\CardGateway;
use App\Services\Shared\Mpesa\MpesaService;
use App\Support\MpesaCallbacks;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MarketplacePaymentService
{
    public function __construct(
        private readonly MpesaService $mpesaService,
        private readonly MarketplaceProductService $productService,
        private ?OrderNotificationService $notifications = null,
        private ?CardGateway $cards = null,
    ) {
        $this->notifications ??= app(OrderNotificationService::class);
        $this->cards ??= app(CardGateway::class);
    }

    // =========================================================================

    /**
     * Initiate payment for an order.
     * Routes to the appropriate handler based on the payment method captured at checkout.
     *
     * @param  array{phone_number?: string}  $paymentData
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
            MarketplacePaymentMethod::Mpesa => $this->initiateMpesaSTKPayment($order, $payment, $paymentData),
            MarketplacePaymentMethod::MpesaPaybill => $this->initiateMpesaPaybillPayment($order, $payment),
            MarketplacePaymentMethod::CashOnDelivery => $this->initiateCashOnDeliveryPayment($order, $payment),
            MarketplacePaymentMethod::Card => $this->initiateCardPayment($order, $payment),
            default => throw new \RuntimeException(
                "Payment method '{$payment->payment_method->label()}' is not yet available. Please choose M-Pesa, card, or M-Pesa Paybill."
            ),
        };
    }

    /**
     * Initiate an M-Pesa STK push for the order.
     *
     * If the payment is already Processing and was initiated <60 seconds ago, returns the
     * current status with a "please wait" message instead of re-initiating.
     *
     * @param  array{phone_number?: string}  $paymentData
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
                'payment' => $payment,
                'message' => 'STK push already sent. Please check your phone.',
                'instructions' => ['wait_seconds' => 60 - $payment->initiated_at->diffInSeconds(now())],
            ];
        }

        try {
            $stkResult = $this->mpesaService->initiateSTKPush(
                phoneNumber: $phoneNumber,
                amount: (float) $payment->amount,
                accountReference: $order->order_number,
                transactionDesc: 'Marketplace order payment',
                callbackUrl: MpesaCallbacks::url('stk'),
            );
        } catch (MpesaException $e) {
            Log::channel('mpesa')->error('Marketplace STK push failed', [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        // Every prompt sent stays matched to this payment: a buyer may approve an earlier one.
        $prompts = array_values(array_unique(array_filter([
            ...($payment->payment_metadata['stk_checkout_request_ids'] ?? []),
            $payment->transaction_reference,
            $stkResult['checkout_request_id'],
        ])));

        $payment->update([
            'payment_status' => MarketplacePaymentStatus::Processing,
            'transaction_reference' => $stkResult['checkout_request_id'],
            'payment_metadata' => array_merge($payment->payment_metadata ?? [], [
                'stk_checkout_request_ids' => $prompts,
                'merchant_request_id' => $stkResult['merchant_request_id'],
                'phone_number' => $phoneNumber,
                'stk_initiated_at' => now()->toIso8601String(),
            ]),
            'initiated_at' => now(),
        ]);

        CentralPaymentLog::record('marketplace_order_payment', $payment->id, 'stk_initiated', [
            'customer_id' => $order->customer_id,
            'amount' => (float) $payment->amount,
            'customer_phone' => $phoneNumber,
            'transaction_reference' => $stkResult['checkout_request_id'],
        ]);

        Log::channel('mpesa')->info('Marketplace STK push initiated', [
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'checkout_request_id' => $stkResult['checkout_request_id'],
        ]);

        event(new PaymentAttempted(
            order: $order,
            payment: $payment->fresh(),
            paymentMethod: MarketplacePaymentMethod::Mpesa->value,
        ));

        return [
            'payment' => $payment->fresh(),
            'message' => 'STK push sent. Please complete payment on your phone.',
            'instructions' => [
                'checkout_request_id' => $stkResult['checkout_request_id'],
                'phone_number' => $phoneNumber,
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
            'amount' => (float) $payment->amount,
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
                'account_number' => $order->order_number,
                'amount' => (float) $payment->amount,
                'expires_at' => $order->payment_deadline_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * Card (Paystack): start a transaction and send the buyer to the gateway's page.
     * Re-initiating while one is in flight returns the same page instead of a second transaction.
     *
     * @return array{payment: MarketplaceOrderPayment, message: string, instructions: array<string, mixed>}
     *
     * @throws CardPaymentException
     */
    private function initiateCardPayment(MarketplaceOrder $order, MarketplaceOrderPayment $payment): array
    {
        $meta = $payment->payment_metadata ?? [];

        if ($payment->payment_status === MarketplacePaymentStatus::Processing && ! empty($meta['authorization_url'])) {
            return [
                'payment' => $payment,
                'message' => 'Card payment already started. Finish it on the payment page.',
                'instructions' => ['authorization_url' => $meta['authorization_url'], 'reference' => $payment->transaction_reference],
            ];
        }

        $order->loadMissing('customer.user');
        $email = $order->customer?->user?->email;

        if (! $email) {
            throw new \RuntimeException('An email address is needed for card payment. Add one to your account first.');
        }

        $reference = 'PCO-'.$order->order_number.'-'.$payment->id.'-'.strtoupper(Str::random(4));
        $callbackUrl = $this->cardCallbackUrl($order);

        try {
            $result = $this->cards->initialize($email, (float) $payment->amount, $reference, $callbackUrl, [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'payment_id' => $payment->id,
                'customer_id' => $order->customer_id,
            ]);
        } catch (CardPaymentException $e) {
            Log::error('Marketplace card initialize failed', ['order_id' => $order->id, 'payment_id' => $payment->id, 'error' => $e->getMessage()]);
            throw $e;
        }

        $payment->update([
            'payment_status' => MarketplacePaymentStatus::Processing,
            'payment_provider' => 'paystack',
            'transaction_reference' => $result['reference'],
            'payment_metadata' => array_merge($meta, [
                'authorization_url' => $result['authorization_url'],
                'access_code' => $result['access_code'],
                'card_initiated_at' => now()->toIso8601String(),
            ]),
            'initiated_at' => now(),
        ]);

        CentralPaymentLog::record('marketplace_order_payment', $payment->id, 'card_initialized', [
            'customer_id' => $order->customer_id,
            'amount' => (float) $payment->amount,
            'transaction_reference' => $result['reference'],
        ]);

        event(new PaymentAttempted(order: $order, payment: $payment->fresh(), paymentMethod: MarketplacePaymentMethod::Card->value));

        return [
            'payment' => $payment->fresh(),
            'message' => 'Continue to the card payment page.',
            'instructions' => ['authorization_url' => $result['authorization_url'], 'reference' => $result['reference']],
        ];
    }

    /**
     * Paystack webhook: the signature must be ours, and the gateway's own record (not the webhook
     * body) decides whether value is given. Idempotent through processPaymentWebhook(). Refund
     * events settle card refunds (RefundService::handleCardRefundEvent).
     *
     * @throws InvalidWebhookSignatureException
     */
    public function handlePaystackWebhook(string $rawBody, ?string $signature): ?MarketplaceOrderPayment
    {
        if (! $this->cards->verifyWebhookSignature($rawBody, $signature)) {
            Log::warning('Paystack webhook with a bad signature ignored');
            throw new InvalidWebhookSignatureException('Invalid webhook signature.');
        }

        $event = json_decode($rawBody, true);
        $reference = (string) ($event['data']['reference'] ?? '');
        $type = (string) ($event['event'] ?? '');

        if (in_array($type, ['refund.processed', 'refund.failed'], true)) {
            app(RefundService::class)->handleCardRefundEvent($type, (array) ($event['data'] ?? []));

            return null;
        }

        if ($reference === '' || ! in_array($type, ['charge.success', 'charge.failed'], true)) {
            Log::info('Paystack webhook ignored', ['event' => $type]);

            return null;
        }

        $payment = MarketplaceOrderPayment::on('central')->where('transaction_reference', $reference)->first();

        if (! $payment) {
            Log::warning('Paystack webhook for an unknown reference', ['reference' => $reference]);

            return null;
        }

        return $this->settleCardPayment($payment);
    }

    /**
     * The buyer is back from the card page (or the app polls): ask the gateway what happened.
     * Only card payments still in flight are checked; anything else is returned as is.
     */
    public function verifyCardPayment(MarketplaceOrderPayment $payment): MarketplaceOrderPayment
    {
        if ($payment->payment_method !== MarketplacePaymentMethod::Card
            || $payment->payment_status !== MarketplacePaymentStatus::Processing
            || ! $payment->transaction_reference) {
            return $payment;
        }

        return $this->settleCardPayment($payment);
    }

    /** Verify the reference with the gateway and give value, fail, or leave it pending. */
    private function settleCardPayment(MarketplaceOrderPayment $payment): MarketplaceOrderPayment
    {
        try {
            $tx = $this->cards->verify($payment->transaction_reference);
        } catch (CardPaymentException $e) {
            Log::error('Paystack verify failed', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);

            return $payment;
        }

        if ($tx['status'] === 'success') {
            if (abs((float) $tx['amount'] - (float) $payment->amount) > 0.009) {
                Log::error('Paystack amount mismatch — not giving value', ['payment_id' => $payment->id, 'expected' => (float) $payment->amount, 'paid' => $tx['amount']]);

                return $this->processPaymentWebhook([
                    'transaction_reference' => $payment->transaction_reference,
                    'status' => 'failed',
                    'failure_reason' => 'Amount paid does not match the order',
                    'failure_code' => 'AMOUNT_MISMATCH',
                ]);
            }

            return $this->processPaymentWebhook([
                'transaction_reference' => $payment->transaction_reference,
                'status' => 'success',
                'provider_reference' => $tx['reference'],
            ]);
        }

        // 'abandoned' and 'ongoing' mean the buyer is still on (or left) the card page: not final.
        if (in_array($tx['status'], ['failed', 'reversed'], true)) {
            return $this->processPaymentWebhook([
                'transaction_reference' => $payment->transaction_reference,
                'status' => 'failed',
                'failure_reason' => $tx['gateway_response'] ?: 'Card payment '.$tx['status'],
                'failure_code' => strtoupper($tx['status']),
            ]);
        }

        return $payment->fresh();
    }

    /** Where the gateway sends the buyer afterwards: the order page on the web, which then verifies. */
    private function cardCallbackUrl(MarketplaceOrder $order): string
    {
        $configured = (string) config('paystack.callback_url');
        $base = $configured !== '' ? $configured : rtrim((string) config('app.frontend_url', config('app.url')), '/').'/account/orders/'.$order->order_number.'/paid';

        return str_contains($base, '{order}') ? str_replace('{order}', $order->order_number, $base) : $base;
    }

    /**
     * Process a C2B Paybill confirmation for a marketplace order.
     * Called by ProcessMpesaC2BConfirmationJob when Safaricom confirms payment.
     *
     * @param  array<string, mixed>  $parsedPayload  Output of MpesaService::parseC2BPayload()
     */
    public function processC2BConfirmation(array $parsedPayload): ?MarketplaceOrderPayment
    {
        $orderNumber = (string) $parsedPayload['bill_ref_number'];
        $transactionId = (string) $parsedPayload['transaction_id'];
        $paid = (float) $parsedPayload['amount'];

        // Idempotency: TransID already applied to an order, or already held as unmatched
        $existing = MarketplaceOrderPayment::on('central')
            ->where('provider_reference', $transactionId)
            ->whereIn('payment_status', [MarketplacePaymentStatus::Completed->value, MarketplacePaymentStatus::Refunded->value])
            ->first();

        if ($existing || UnmatchedMpesaPayment::query()->where('transaction_id', $transactionId)->exists()) {
            Log::channel('mpesa')->info('C2B marketplace confirmation — duplicate ignored', ['transaction_id' => $transactionId]);

            return $existing;
        }

        $order = MarketplaceOrder::on('central')->where('order_number', strtoupper(trim($orderNumber)))->first();

        if ($order === null) {
            $this->holdUnmatched($parsedPayload, $transactionId, $paid, null, 'No order with this number');

            return null;
        }

        CentralPaymentLog::record('marketplace_order_payment', 0, 'c2b_confirmation_received', [
            'customer_id' => $order->customer_id,
            'amount' => $paid,
            'customer_phone' => $parsedPayload['phone'],
            'transaction_reference' => $transactionId,
            'raw_payload' => $parsedPayload,
        ]);

        $refundLate = null;

        $result = DB::connection('central')->transaction(function () use ($order, $parsedPayload, $transactionId, $paid, &$refundLate) {
            $order = MarketplaceOrder::on('central')->lockForUpdate()->find($order->id);

            if ($order->payments()->whereIn('payment_status', [MarketplacePaymentStatus::Completed->value, MarketplacePaymentStatus::Refunded->value])->exists()) {
                $this->holdUnmatched($parsedPayload, $transactionId, $paid, $order, 'Order was already paid');

                return null;
            }

            $payment = $order->payments()
                ->whereIn('payment_status', [
                    MarketplacePaymentStatus::Pending->value,
                    MarketplacePaymentStatus::Processing->value,
                    MarketplacePaymentStatus::Failed->value,
                ])
                ->latest('id')
                ->first();

            if ($payment === null) {
                $this->holdUnmatched($parsedPayload, $transactionId, $paid, $order, 'No payment was expected on this order');

                return null;
            }

            // M-Pesa only moves whole shillings: the order is paid when the rounded-up total arrived.
            $due = (float) ceil((float) $payment->amount);

            if ($paid + 0.009 < $due) {
                $this->holdUnmatched($parsedPayload, $transactionId, $paid, $order, 'Paid less than the order total of KSh '.number_format($due, 2));

                return null;
            }

            if ($paid - 0.009 > $due) {
                $this->holdUnmatched($parsedPayload, $transactionId.'-X', round($paid - $due, 2), $order, 'Paid more than the order total; the extra is to be returned');
            }

            if ($order->order_status->isTerminal()) {
                $payment->markAsCompleted(null, $transactionId);
                $refundLate = $order;

                return $payment->fresh();
            }

            $this->confirmPayment($payment, null, $transactionId);

            return $payment->fresh();
        });

        if ($refundLate !== null) {
            $this->refundLatePayment($refundLate);
        }

        return $result;
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
        $codReference = 'COD-'.$order->order_number.'-'.now()->timestamp;

        // Fire analytics event for payment attempt (COD)
        event(new PaymentAttempted(
            order: $order,
            payment: $payment,
            paymentMethod: MarketplacePaymentMethod::CashOnDelivery->value,
        ));

        $this->confirmPayment($payment, $codReference);

        Log::info('Cash on delivery confirmed', [
            'order_id' => $order->id,
            'payment_id' => $payment->id,
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
        $transactionReference = (string) $webhookData['transaction_reference'];
        $receipt = $webhookData['provider_reference'] ?? null;
        $isSuccess = ($webhookData['status'] ?? '') === 'success';

        $payment = MarketplaceOrderPayment::on('central')->where('transaction_reference', $transactionReference)->first()
            // An earlier STK prompt for the same payment (the buyer approved the first one).
            ?? MarketplaceOrderPayment::on('central')->whereJsonContains('payment_metadata->stk_checkout_request_ids', $transactionReference)->first();

        if ($payment === null) {
            if ($isSuccess && filled($receipt)) {
                $this->holdUnmatched($webhookData, (string) $receipt, (float) ($webhookData['amount'] ?? 0), null, 'Payment for an unknown checkout request');
            }

            throw (new ModelNotFoundException)->setModel(MarketplaceOrderPayment::class, [$transactionReference]);
        }

        if (in_array($payment->payment_status, [MarketplacePaymentStatus::Completed, MarketplacePaymentStatus::Refunded], true)) {
            // Same receipt again: a duplicate callback. A different receipt: the buyer paid twice.
            if ($isSuccess && filled($receipt) && $payment->provider_reference !== $receipt && $payment->transaction_reference !== $receipt) {
                $this->holdUnmatched($webhookData, (string) $receipt, (float) ($webhookData['amount'] ?? $payment->amount), $payment->order, 'Second payment for an order already paid');
            } else {
                Log::info('Duplicate payment webhook ignored', ['transaction_reference' => $transactionReference, 'payment_id' => $payment->id]);
            }

            return $payment;
        }

        if (! $isSuccess) {
            // A failure for an older prompt must not undo a newer one still waiting.
            if ($payment->transaction_reference === $transactionReference && $payment->payment_status !== MarketplacePaymentStatus::Failed) {
                $this->handlePaymentFailure(
                    $payment,
                    $webhookData['failure_reason'] ?? 'Payment failed',
                    $webhookData['failure_code'] ?? null
                );
            }

            return $payment->fresh();
        }

        $refundLate = null;

        // Race condition guard: lock order row, check if already cancelled
        $result = DB::connection('central')->transaction(function () use ($payment, $webhookData, $receipt, &$refundLate) {
            $order = MarketplaceOrder::on('central')
                ->lockForUpdate()
                ->findOrFail($payment->order_id);
            $payment = MarketplaceOrderPayment::on('central')->lockForUpdate()->find($payment->id);

            if ($payment->payment_status === MarketplacePaymentStatus::Completed) {
                return $payment;
            }

            if ($order->order_status->isTerminal()) {
                Log::info('Payment arrived for cancelled/terminal order — refunding it', [
                    'order_id' => $order->id,
                    'order_status' => $order->order_status->value,
                    'payment_id' => $payment->id,
                ]);

                // Recorded as received (with its receipt) so the refund can go back the same way.
                $payment->markAsCompleted(null, $receipt);
                $refundLate = $order;

                return $payment->fresh();
            }

            $this->confirmPayment($payment, null, $receipt);

            return $payment->fresh();
        });

        if ($refundLate !== null) {
            $this->refundLatePayment($refundLate);
        }

        return $result;
    }

    /** Money that reached a cancelled order goes back the way it came (reversal, B2C or card). */
    private function refundLatePayment(MarketplaceOrder $order): void
    {
        $payment = $order->payments()->where('payment_status', MarketplacePaymentStatus::Completed->value)->latest('id')->first();

        if ($payment === null) {
            return;
        }

        app(RefundService::class)->start($order, (float) $payment->amount, 'late_payment', 'Paid after the order was cancelled');
    }

    /**
     * Marketplace money that matched no open order: held for finance to send back (never lost,
     * never credited to a shop). Idempotent on the M-Pesa receipt.
     *
     * @param  array<string, mixed>  $payload
     */
    private function holdUnmatched(array $payload, string $receipt, float $amount, ?MarketplaceOrder $order, string $note): void
    {
        Log::channel('mpesa')->warning('Marketplace payment left unmatched', ['receipt' => $receipt, 'order_id' => $order?->id, 'note' => $note]);

        UnmatchedMpesaPayment::query()->firstOrCreate(['transaction_id' => mb_substr($receipt, 0, 40)], [
            'source' => 'marketplace',
            'amount' => $amount,
            'bill_ref_number' => isset($payload['bill_ref_number']) ? mb_substr((string) $payload['bill_ref_number'], 0, 40) : null,
            'order_number' => $order?->order_number,
            'phone_masked' => filled($payload['phone'] ?? null) ? PhoneNumber::mask((string) $payload['phone']) : null,
            'payer_name' => isset($payload['first_name']) ? mb_substr((string) $payload['first_name'], 0, 120) : null,
            'note' => mb_substr($note, 0, 255),
        ]);
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
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'amount' => $payment->amount,
            'method' => $payment->payment_method->value,
        ]);

        $this->notifications->notifyCustomer($order, 'payment_confirmed', $payment->fresh());

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
            'order_id' => $payment->order_id,
            'payment_id' => $payment->id,
            'reason' => $reason,
        ]);

        $this->notifications->notifyCustomer($payment->order, 'payment_failed', $payment->fresh(), [
            'reason' => $reason,
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
        // Decided under the same order lock the payment callbacks take, so a payment that lands
        // while this runs is either seen here (order kept) or arrives after (and is refunded).
        $cancelled = DB::connection('central')->transaction(function () use ($order) {
            $order = MarketplaceOrder::on('central')->lockForUpdate()->find($order->id);

            if ($order === null || $order->order_status->isTerminal()) {
                return false;
            }

            if ($order->payments()->whereIn('payment_status', [MarketplacePaymentStatus::Completed->value, MarketplacePaymentStatus::Refunded->value])->exists()) {
                return false;
            }

            $order->payments()
                ->whereIn('payment_status', [
                    MarketplacePaymentStatus::Pending,
                    MarketplacePaymentStatus::Processing,
                ])
                ->update([
                    'payment_status' => MarketplacePaymentStatus::Failed,
                    'failed_at' => now(),
                    'failure_reason' => 'Payment deadline exceeded',
                ]);

            $order->update([
                'reservation_status' => ReservationStatus::Released,
            ]);

            $order->cancel('Payment deadline exceeded — reservation released.');

            return true;
        });

        if (! $cancelled) {
            return;
        }

        ProcessOrderCancellation::dispatch($order->id);

        Log::info('Payment deadline exceeded — order cancelled', [
            'order_id' => $order->id,
        ]);

        $this->notifications->notifyCustomer($order->fresh(), 'payment_timeout', context: [
            'reason' => 'Payment deadline exceeded',
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
