<?php

namespace App\Services\Shared\Mpesa;

use App\Exceptions\MpesaException;
use App\Helpers\PhoneNumber;
use App\Support\MpesaCallbacks;
use Illuminate\Support\Facades\Log;

/**
 * M-Pesa facade used by the payment services: builds requests, parses Safaricom callbacks and
 * hands the network calls to the configured {@see MpesaGateway} (Daraja or the local fake).
 */
class MpesaService
{
    public function __construct(private ?MpesaGateway $gateway = null) {}

    /** The configured gateway driver. */
    public function gateway(): MpesaGateway
    {
        return $this->gateway ??= app(MpesaGateway::class);
    }

    /**
     * Return the active credential block (sandbox or production) from config.
     *
     * @return array{base_url: string, consumer_key: string, consumer_secret: string, shortcode: string, passkey: string}
     */
    public function getActiveCredentials(): array
    {
        $env = config('mpesa.environment', 'sandbox');

        return config("mpesa.{$env}");
    }

    /**
     * Initiate an M-Pesa STK Push (M-Pesa Express) to a customer's phone.
     *
     * @param  string|null  $callbackUrl  Override the default STK callback URL.
     * @return array{checkout_request_id: string, merchant_request_id: string}
     *
     * @throws MpesaException|\InvalidArgumentException
     */
    public function initiateSTKPush(
        string $phoneNumber,
        float $amount,
        string $accountReference,
        string $transactionDesc,
        ?string $callbackUrl = null,
    ): array {
        $callbackUrl = $callbackUrl ?? MpesaCallbacks::url('stk');
        $phone = PhoneNumber::normalize($phoneNumber);

        Log::channel('mpesa')->info('Initiating STK push', [
            'phone' => PhoneNumber::mask($phoneNumber),
            'amount' => $amount,
            'ref' => $accountReference,
        ]);

        $result = $this->gateway()->stkPush($phone, $amount, $accountReference, $transactionDesc, $callbackUrl);

        $checkoutRequestId = $result['checkout_request_id'];
        $merchantRequestId = $result['merchant_request_id'];

        Log::channel('mpesa')->info('STK push initiated successfully', [
            'checkout_request_id' => $checkoutRequestId,
            'phone' => PhoneNumber::mask($phoneNumber),
            'amount' => $amount,
        ]);

        return [
            'checkout_request_id' => $checkoutRequestId,
            'merchant_request_id' => $merchantRequestId,
        ];
    }

    /**
     * Register C2B (Paybill) ValidationURL and ConfirmationURL with Safaricom.
     *
     * This is a one-time setup per shortcode per environment.
     * Run via `php artisan mpesa:register-c2b`.
     *
     * @param  string  $responseType  'Cancelled' (strict) or 'Completed' (lenient).
     *
     * @throws MpesaException
     */
    public function registerC2BUrls(
        string $validationUrl,
        string $confirmationUrl,
        string $responseType = 'Cancelled',
    ): array {
        Log::channel('mpesa')->info('Registering C2B URLs', [
            'validation_url' => $validationUrl,
            'confirmation_url' => $confirmationUrl,
            'response_type' => $responseType,
            'environment' => config('mpesa.environment'),
        ]);

        $result = $this->gateway()->registerC2BUrls($validationUrl, $confirmationUrl, $responseType);

        Log::channel('mpesa')->info('C2B URLs registered successfully', [
            'response' => $result,
        ]);

        return $result;
    }

    /**
     * Simulate a C2B payment (Daraja sandbox or the fake driver; not in production).
     * Useful for testing ValidationURL and ConfirmationURL handlers.
     *
     * @throws MpesaException
     */
    public function simulateC2BPayment(
        string $phoneNumber,
        float $amount,
        string $billRefNumber,
    ): array {
        $result = $this->gateway()->simulateC2B(PhoneNumber::normalize($phoneNumber), $amount, $billRefNumber);

        Log::channel('mpesa')->info('C2B simulation triggered', [
            'bill_ref_number' => $billRefNumber,
            'amount' => $amount,
            'phone' => PhoneNumber::mask($phoneNumber),
        ]);

        return $result;
    }

    /**
     * Parse an STK Push callback payload from Safaricom into a normalised array.
     *
     * @return array{transaction_reference: string|null, status: string, provider_reference: string|null, failure_reason: string|null, failure_code: string|null, amount: float|null, phone: string|null}
     */
    public function parseSTKCallbackPayload(array $payload): array
    {
        $callback = $payload['Body']['stkCallback'] ?? [];
        $resultCode = $callback['ResultCode'] ?? null;
        $resultDesc = $callback['ResultDesc'] ?? null;
        $checkoutReqId = $callback['CheckoutRequestID'] ?? null;
        $isSuccess = $resultCode === 0;

        $mpesaReceiptNumber = null;
        $amount = null;
        $phone = null;

        if ($isSuccess) {
            foreach ($callback['CallbackMetadata']['Item'] ?? [] as $item) {
                match ($item['Name'] ?? null) {
                    'MpesaReceiptNumber' => $mpesaReceiptNumber = $item['Value'] ?? null,
                    'Amount' => $amount = isset($item['Value']) ? (float) $item['Value'] : null,
                    'PhoneNumber' => $phone = isset($item['Value']) ? (string) $item['Value'] : null,
                    default => null,
                };
            }
        }

        Log::channel('mpesa')->info('STK callback received', [
            'checkout_request_id' => $checkoutReqId,
            'result_code' => $resultCode,
            'result_desc' => $resultDesc,
            'receipt' => $mpesaReceiptNumber,
        ]);

        return [
            'transaction_reference' => $checkoutReqId,
            'status' => $isSuccess ? 'success' : 'failed',
            'provider_reference' => $mpesaReceiptNumber,
            'failure_reason' => $isSuccess ? null : $resultDesc,
            'failure_code' => $isSuccess ? null : (string) $resultCode,
            'amount' => $amount,
            'phone' => $phone,
        ];
    }

    /**
     * Parse a C2B payload (validation or confirmation) into a normalised array.
     * The structure is identical for both; ConfirmationURL additionally includes OrgAccountBalance.
     *
     * @return array{transaction_id: string|null, transaction_type: string|null, transaction_time: string|null, amount: float, shortcode: string|null, bill_ref_number: string|null, phone: string|null, first_name: string|null, last_name: string|null, org_account_balance: string|null}
     */
    public function parseC2BPayload(array $payload): array
    {
        Log::channel('mpesa')->info('C2B payload received', [
            'bill_ref_number' => $payload['BillRefNumber'] ?? null,
            'trans_id' => $payload['TransID'] ?? null,
            'amount' => $payload['TransAmount'] ?? null,
        ]);

        return [
            'transaction_id' => $payload['TransID'] ?? null,
            'transaction_type' => $payload['TransactionType'] ?? null,
            'transaction_time' => $payload['TransTime'] ?? null,
            'amount' => (float) ($payload['TransAmount'] ?? 0),
            'shortcode' => $payload['BusinessShortCode'] ?? null,
            'bill_ref_number' => $payload['BillRefNumber'] ?? null,
            'phone' => $payload['MSISDN'] ?? null,
            'first_name' => $payload['FirstName'] ?? null,
            'last_name' => $payload['LastName'] ?? null,
            'org_account_balance' => $payload['OrgAccountBalance'] ?? null,
        ];
    }

    /**
     * Build the JSON response Safaricom expects from our ValidationURL.
     */
    public function buildC2BValidationResponse(bool $accepted, string $reason = ''): array
    {
        if ($accepted) {
            return ['ResultCode' => '0', 'ResultDesc' => 'Accepted'];
        }

        return ['ResultCode' => 'C2B00011', 'ResultDesc' => $reason ?: 'Invalid Account'];
    }

    /**
     * Build the JSON response Safaricom expects from our ConfirmationURL.
     */
    public function buildC2BConfirmationResponse(): array
    {
        return ['ResultCode' => 0, 'ResultDesc' => 'Success'];
    }
}
