<?php

namespace App\Services\Central\Subscription;

use App\Enums\Central\SubscriptionPaymentStatus;
use App\Exceptions\MpesaException;
use App\Models\BusinessDetail;
use App\Models\BusinessSubscription;
use App\Models\CentralPaymentLog;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Services\Shared\Mpesa\MpesaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionPaymentService
{
    /** Seconds before allowing a new STK push for the same tenant. */
    private const STK_COOLDOWN_SECONDS = 60;

    /** Amount tolerance in KES for plan matching. */
    private const AMOUNT_TOLERANCE = 0.01;

    public function __construct(
        private readonly MpesaService $mpesa,
    ) {}

    // =========================================================================
    // STK Push flow
    // =========================================================================

    /**
     * Initiate an M-Pesa STK push for the tenant's registered business phone.
     *
     * @return array{payment: SubscriptionPayment, message: string, instructions: array<string, mixed>}
     *
     * @throws MpesaException|\InvalidArgumentException
     */
    public function initiateSTKPayment(string $tenantId, int $planId): array
    {
        $plan = SubscriptionPlan::on('central')
            ->where('id', $planId)
            ->where('is_active', true)
            ->firstOrFail();

        $businessDetail = BusinessDetail::on('central')
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $phone = $businessDetail->business_phone;

        // Cooldown guard: if an STK push was sent within the last 60 seconds, don't resend
        $recentProcessing = SubscriptionPayment::on('central')
            ->where('tenant_id', $tenantId)
            ->where('payment_type', 'stk')
            ->where('payment_status', SubscriptionPaymentStatus::Processing)
            ->where('initiated_at', '>=', now()->subSeconds(self::STK_COOLDOWN_SECONDS))
            ->latest('initiated_at')
            ->first();

        if ($recentProcessing) {
            $waitSeconds = self::STK_COOLDOWN_SECONDS - $recentProcessing->initiated_at->diffInSeconds(now());

            return [
                'payment'      => $recentProcessing,
                'message'      => 'STK push already sent. Please check your phone.',
                'instructions' => ['wait_seconds' => max(0, $waitSeconds)],
            ];
        }

        $payment = SubscriptionPayment::create([
            'tenant_id'            => $tenantId,
            'subscription_plan_id' => $plan->id,
            'customer_phone'       => $phone,
            'amount'               => $plan->price,
            'payment_status'       => SubscriptionPaymentStatus::Pending,
            'payment_type'         => 'stk',
        ]);

        try {
            $stkResult = $this->mpesa->initiateSTKPush(
                phoneNumber:      $phone,
                amount:           (float) $plan->price,
                accountReference: config('mpesa.account_prefix') . 'SUB',
                transactionDesc:  $plan->name,
                callbackUrl:      config('mpesa.subscription_stk_callback_url'),
            );

            $payment->update([
                'payment_status'        => SubscriptionPaymentStatus::Processing,
                'transaction_reference' => $stkResult['checkout_request_id'],
                'initiated_at'          => now(),
                'payment_metadata'      => [
                    'merchant_request_id' => $stkResult['merchant_request_id'],
                    'plan_name'           => $plan->name,
                ],
            ]);

            CentralPaymentLog::record('subscription_payment', $payment->id, 'stk_initiated', [
                'tenant_id'    => $tenantId,
                'amount'       => (float) $plan->price,
                'customer_phone' => $phone,
                'transaction_reference' => $stkResult['checkout_request_id'],
            ]);

            return [
                'payment'      => $payment->fresh(),
                'message'      => 'STK push sent. Please complete payment on your phone.',
                'instructions' => [
                    'checkout_request_id' => $stkResult['checkout_request_id'],
                    'phone_number'        => $phone, // the business phone that received the push
                ],
            ];
        } catch (MpesaException $e) {
            $payment->markAsFailed($e->getMessage(), $e->darajaErrorCode);

            CentralPaymentLog::record('subscription_payment', $payment->id, 'payment_failed', [
                'tenant_id'    => $tenantId,
                'result_code'  => $e->darajaErrorCode,
                'result_description' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Process an STK Push callback from Safaricom.
     * Finds the pending payment by CheckoutRequestID and activates the subscription on success.
     *
     * @param  array{transaction_reference: string|null, status: string, provider_reference: string|null, failure_reason: string|null, failure_code: string|null}  $parsedPayload
     */
    public function processSTKCallback(array $parsedPayload): SubscriptionPayment
    {
        $transactionReference = $parsedPayload['transaction_reference'];

        // Idempotency: already completed
        $existing = SubscriptionPayment::on('central')
            ->where('transaction_reference', $transactionReference)
            ->where('payment_status', SubscriptionPaymentStatus::Completed)
            ->first();

        if ($existing) {
            return $existing;
        }

        $payment = SubscriptionPayment::on('central')
            ->where('transaction_reference', $transactionReference)
            ->firstOrFail();

        $isSuccess = ($parsedPayload['status'] ?? '') === 'success';

        if (! $isSuccess) {
            $payment->markAsFailed(
                $parsedPayload['failure_reason'] ?? 'Payment failed',
                $parsedPayload['failure_code'] ?? null,
            );

            CentralPaymentLog::record('subscription_payment', $payment->id, 'payment_failed', [
                'tenant_id'          => $payment->tenant_id,
                'result_code'        => $parsedPayload['failure_code'],
                'result_description' => $parsedPayload['failure_reason'],
            ]);

            Log::channel('mpesa')->info('Subscription STK payment failed', [
                'payment_id' => $payment->id,
                'reason'     => $parsedPayload['failure_reason'],
            ]);

            return $payment->fresh();
        }

        return $this->activateSubscription($payment, $parsedPayload['provider_reference']);
    }

    // =========================================================================
    // C2B Paybill flow
    // =========================================================================

    /**
     * Process a C2B Paybill confirmation for a subscription payment.
     *
     * Called by ProcessMpesaC2BConfirmationJob after Safaricom sends confirmation.
     * The tenant has already paid via M-Pesa Paybill menu — this makes it official.
     *
     * @param  array<string, mixed>  $parsedPayload  Output of MpesaService::parseC2BPayload()
     */
    public function processC2BConfirmation(array $parsedPayload): SubscriptionPayment
    {
        $transactionId   = $parsedPayload['transaction_id'];
        $billRefNumber   = $parsedPayload['bill_ref_number'];
        $amount          = $parsedPayload['amount'];
        $customerPhone   = $parsedPayload['phone'];

        // Idempotency: TransID already processed
        $existing = SubscriptionPayment::on('central')
            ->where('transaction_reference', $transactionId)
            ->where('payment_status', SubscriptionPaymentStatus::Completed)
            ->first();

        if ($existing) {
            Log::channel('mpesa')->info('C2B subscription confirmation — duplicate ignored', [
                'transaction_id' => $transactionId,
                'payment_id'     => $existing->id,
            ]);

            return $existing;
        }

        $tenant = Tenant::on('central')
            ->where('mpesa_paybill_account', $billRefNumber)
            ->firstOrFail();

        $plan = SubscriptionPlan::on('central')
            ->where('is_active', true)
            ->whereRaw('ABS(CAST(price AS DECIMAL(10,2)) - ?) <= ?', [$amount, self::AMOUNT_TOLERANCE])
            ->firstOrFail();

        $payment = SubscriptionPayment::create([
            'tenant_id'             => $tenant->id,
            'subscription_plan_id'  => $plan->id,
            'customer_phone'        => $customerPhone,
            'amount'                => $amount,
            'payment_status'        => SubscriptionPaymentStatus::Pending,
            'payment_type'          => 'c2b',
            'bill_ref_number'       => $billRefNumber,
            'transaction_reference' => $transactionId,
        ]);

        CentralPaymentLog::record('subscription_payment', $payment->id, 'c2b_confirmation_received', [
            'tenant_id'            => $tenant->id,
            'amount'               => $amount,
            'customer_phone'       => $customerPhone,
            'transaction_reference' => $transactionId,
            'raw_payload'          => $parsedPayload,
        ]);

        return $this->activateSubscription($payment, $parsedPayload['transaction_id']);
    }

    /**
     * Return Paybill payment instructions for the tenant to display in their app.
     * No API call needed — just returns the shortcode and their unique account number.
     *
     * @return array{shortcode: string, account_number: string, plans: array<int, array{id: int, name: string, price: float, billing_cycle: string}>}
     */
    public function getPaybillInstructions(string $tenantId): array
    {
        $tenant = Tenant::on('central')->findOrFail($tenantId);

        $plans = SubscriptionPlan::on('central')
            ->where('is_active', true)
            ->orderBy('price')
            ->get()
            ->map(fn($plan) => [
                'id'            => $plan->id,
                'name'          => $plan->name,
                'price'         => (float) $plan->price,
                'billing_cycle' => $plan->getBillingCycleDisplay(),
            ])
            ->all();

        $credentials = $this->mpesa->getActiveCredentials();

        return [
            'shortcode'      => $credentials['shortcode'],
            'account_number' => $tenant->mpesa_paybill_account ?? '—',
            'plans'          => $plans,
        ];
    }

    /**
     * Retrieve the most recent subscription payment for a tenant.
     */
    public function getLatestPayment(string $tenantId): ?SubscriptionPayment
    {
        return SubscriptionPayment::on('central')
            ->where('tenant_id', $tenantId)
            ->latest('id')
            ->first();
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Create the BusinessSubscription and mark the payment as completed.
     * Wrapped in a DB transaction for atomicity.
     */
    private function activateSubscription(SubscriptionPayment $payment, ?string $providerReference): SubscriptionPayment
    {
        return DB::connection('central')->transaction(function () use ($payment, $providerReference) {
            $plan = $payment->subscriptionPlan;

            $endDate = $plan->billing_cycle_days > 0
                ? now()->addDays($plan->billing_cycle_days)->toDateString()
                : null;

            $subscription = BusinessSubscription::create([
                'tenant_id'            => $payment->tenant_id,
                'subscription_plan_id' => $plan->id,
                'start_date'           => now()->toDateString(),
                'end_date'             => $endDate,
                'amount_paid'          => $payment->amount,
                'currency'             => 'KES',
                'payment_method'       => 'mpesa',
                'payment_reference'    => $providerReference,
                'payment_date'         => now(),
                'status'               => 'active',
                'auto_renew'           => false,
                'is_trial'             => false,
            ]);

            $payment->update([
                'payment_status'           => SubscriptionPaymentStatus::Completed,
                'provider_reference'       => $providerReference,
                'completed_at'             => now(),
                'business_subscription_id' => $subscription->id,
            ]);

            CentralPaymentLog::record('subscription_payment', $payment->id, 'subscription_activated', [
                'tenant_id'       => $payment->tenant_id,
                'subscription_id' => $subscription->id,
                'plan_id'         => $plan->id,
                'provider_reference' => $providerReference,
                'amount'          => (float) $payment->amount,
            ]);

            Log::channel('mpesa')->info('Subscription activated', [
                'tenant_id'       => $payment->tenant_id,
                'plan_id'         => $plan->id,
                'subscription_id' => $subscription->id,
                'payment_type'    => $payment->payment_type,
                'receipt'         => $providerReference,
            ]);

            return $payment->fresh();
        });
    }
}
