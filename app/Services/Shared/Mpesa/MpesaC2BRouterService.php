<?php

namespace App\Services\Shared\Mpesa;

use App\Jobs\Central\ProcessMpesaC2BConfirmationJob;
use App\Models\MarketplaceOrder;
use App\Models\Tenant;
use App\Services\Central\Billing\BillingTopupService;
use Illuminate\Support\Facades\Log;

class MpesaC2BRouterService
{
    /** Amount tolerance in KES — allows for floating-point rounding differences. */
    private const AMOUNT_TOLERANCE = 0.01;

    /**
     * Handle an incoming C2B ValidationURL request.
     *
     * Returns the Safaricom-compliant JSON response array (accept or reject).
     * Must complete within ~5 seconds (Safaricom's timeout).
     *
     * @return array{ResultCode: string, ResultDesc: string}
     */
    public function handleValidation(array $parsedPayload): array
    {
        $billRefNumber = $parsedPayload['bill_ref_number'] ?? '';
        $amount = $parsedPayload['amount'] ?? 0.0;

        Log::channel('mpesa')->info('C2B validation request', [
            'bill_ref_number' => $billRefNumber,
            'amount' => $amount,
        ]);

        return $this->isShopAccountPayment($billRefNumber)
            ? $this->validateShopTopup($billRefNumber, $amount)
            : $this->validateMarketplacePayment($billRefNumber, $amount);
    }

    /**
     * Handle an incoming C2B ConfirmationURL request.
     *
     * Immediately dispatches a queued job so we respond to Safaricom within timeout.
     * All heavy processing (DB writes, subscription activation) happens in the job.
     */
    public function handleConfirmation(array $parsedPayload): void
    {
        $billRefNumber = $parsedPayload['bill_ref_number'] ?? '';
        $paymentType = $this->isShopAccountPayment($billRefNumber) ? 'billing' : 'marketplace';

        Log::channel('mpesa')->info('C2B confirmation received — dispatching job', [
            'bill_ref_number' => $billRefNumber,
            'payment_type' => $paymentType,
            'transaction_id' => $parsedPayload['transaction_id'],
            'amount' => $parsedPayload['amount'],
        ]);

        ProcessMpesaC2BConfirmationJob::dispatch($parsedPayload, $paymentType);
    }

    /**
     * Determine if a BillRefNumber is a shop's Poachy account (top-up of its prepaid balance).
     * Shop account numbers start with the configured prefix (e.g. 'POA').
     */
    private function isShopAccountPayment(string $billRefNumber): bool
    {
        return str_starts_with(
            strtoupper($billRefNumber),
            strtoupper(config('mpesa.account_prefix', 'POA')),
        );
    }

    /**
     * Validate a Paybill top-up to a shop's Poachy account: the account must exist and the
     * amount must be at least the minimum top-up (any amount above it is fine).
     *
     * @return array{ResultCode: string, ResultDesc: string}
     */
    private function validateShopTopup(string $billRefNumber, float $amount): array
    {
        $tenant = Tenant::on('central')
            ->where('mpesa_paybill_account', strtoupper(trim($billRefNumber)))
            ->first();

        if (! $tenant) {
            Log::channel('mpesa')->warning('C2B validation rejected — unknown shop account', [
                'bill_ref_number' => $billRefNumber,
            ]);

            return ['ResultCode' => 'C2B00011', 'ResultDesc' => 'Invalid Account Number'];
        }

        $minimum = app(BillingTopupService::class)->minimumTopup();

        if ($amount + self::AMOUNT_TOLERANCE < $minimum) {
            Log::channel('mpesa')->warning('C2B validation rejected — below the minimum top-up', [
                'bill_ref_number' => $billRefNumber,
                'tenant_id' => $tenant->id,
                'amount' => $amount,
                'minimum' => $minimum,
            ]);

            return ['ResultCode' => 'C2B00013', 'ResultDesc' => 'Invalid Amount — minimum is KSh '.number_format($minimum)];
        }

        Log::channel('mpesa')->info('C2B validation accepted — shop top-up', [
            'bill_ref_number' => $billRefNumber,
            'tenant_id' => $tenant->id,
            'amount' => $amount,
        ]);

        return ['ResultCode' => '0', 'ResultDesc' => 'Accepted'];
    }

    /**
     * Validate a marketplace C2B payment.
     * Checks: order exists, is awaiting payment, and amount matches.
     *
     * @return array{ResultCode: string, ResultDesc: string}
     */
    private function validateMarketplacePayment(string $billRefNumber, float $amount): array
    {
        $order = MarketplaceOrder::on('central')
            ->where('order_number', $billRefNumber)
            ->first();

        if (! $order) {
            Log::channel('mpesa')->warning('C2B validation rejected — order not found', [
                'bill_ref_number' => $billRefNumber,
            ]);

            return ['ResultCode' => 'C2B00011', 'ResultDesc' => 'Order Not Found'];
        }

        if (! $order->canAcceptPayment()) {
            Log::channel('mpesa')->warning('C2B validation rejected — order cannot accept payment', [
                'order_number' => $billRefNumber,
                'order_status' => $order->order_status,
            ]);

            return ['ResultCode' => 'C2B00013', 'ResultDesc' => 'Order Cannot Accept Payment'];
        }

        $expectedAmount = (float) $order->total_amount;

        if (abs($expectedAmount - $amount) > self::AMOUNT_TOLERANCE) {
            Log::channel('mpesa')->warning('C2B validation rejected — amount mismatch', [
                'order_number' => $billRefNumber,
                'expected_amount' => $expectedAmount,
                'paid_amount' => $amount,
            ]);

            return ['ResultCode' => 'C2B00012', 'ResultDesc' => 'Incorrect Amount'];
        }

        Log::channel('mpesa')->info('C2B validation accepted — marketplace', [
            'order_number' => $billRefNumber,
            'amount' => $amount,
        ]);

        return ['ResultCode' => '0', 'ResultDesc' => 'Accepted'];
    }
}
