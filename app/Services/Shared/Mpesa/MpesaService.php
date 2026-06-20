<?php

namespace App\Services\Shared\Mpesa;

use App\Exceptions\MpesaException;
use App\Helpers\PhoneNumber;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MpesaService
{
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
     * Fetch (and cache) an OAuth2 access token from Daraja.
     * Tokens are valid for 60 minutes; we cache for 58 to refresh slightly early.
     *
     * @throws MpesaException
     */
    public function getAccessToken(): string
    {
        $env         = config('mpesa.environment', 'sandbox');
        $cacheKey    = "mpesa_access_token_{$env}";
        $credentials = $this->getActiveCredentials();

        return Cache::remember($cacheKey, 3480, function () use ($credentials, $env) {
            $response = Http::withBasicAuth(
                $credentials['consumer_key'],
                $credentials['consumer_secret'],
            )->get($credentials['base_url'] . '/oauth/v1/generate', [
                'grant_type' => 'client_credentials',
            ]);

            if (! $response->successful() || ! $response->json('access_token')) {
                Log::channel('mpesa')->error('Daraja OAuth token request failed', [
                    'environment' => $env,
                    'status'      => $response->status(),
                    'body'        => $response->body(),
                ]);

                throw MpesaException::oauthFailed($response->body());
            }

            Log::channel('mpesa')->debug('Daraja OAuth token obtained', ['environment' => $env]);

            return $response->json('access_token');
        });
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
        $credentials = $this->getActiveCredentials();
        $shortcode   = $credentials['shortcode'];
        $passkey     = $credentials['passkey'];
        $timestamp   = now()->format('YmdHis');
        $password    = base64_encode($shortcode . $passkey . $timestamp);
        $callbackUrl = $callbackUrl ?? config('mpesa.stk_callback_url');
        $phone       = PhoneNumber::normalize($phoneNumber);

        Log::channel('mpesa')->info('Initiating STK push', [
            'phone'  => PhoneNumber::mask($phoneNumber),
            'amount' => $amount,
            'ref'    => $accountReference,
        ]);

        $response = Http::withToken($this->getAccessToken())
            ->post($credentials['base_url'] . '/mpesa/stkpush/v1/processrequest', [
                'BusinessShortCode' => $shortcode,
                'Password'          => $password,
                'Timestamp'         => $timestamp,
                'TransactionType'   => 'CustomerPayBillOnline',
                'Amount'            => (int) ceil($amount), // M-Pesa requires integer amounts
                'PartyA'            => $phone,
                'PartyB'            => $shortcode,
                'PhoneNumber'       => $phone,
                'CallBackURL'       => $callbackUrl,
                'AccountReference'  => substr($accountReference, 0, 12), // Daraja max 12 chars
                'TransactionDesc'   => substr($transactionDesc, 0, 13),  // Daraja max 13 chars
            ]);

        if (! $response->successful() || isset($response->json()['errorCode'])) {
            $error = $response->json('errorMessage')
                ?? $response->json('ResponseDescription')
                ?? 'STK push failed';

            Log::channel('mpesa')->error('STK push failed', [
                'status'   => $response->status(),
                'response' => $response->json(),
                'phone'    => PhoneNumber::mask($phoneNumber),
            ]);

            throw MpesaException::stkPushFailed($error, [
                'status'   => $response->status(),
                'response' => $response->json(),
            ]);
        }

        $checkoutRequestId = $response->json('CheckoutRequestID');
        $merchantRequestId = $response->json('MerchantRequestID');

        Log::channel('mpesa')->info('STK push initiated successfully', [
            'checkout_request_id' => $checkoutRequestId,
            'phone'               => PhoneNumber::mask($phoneNumber),
            'amount'              => $amount,
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
        $credentials = $this->getActiveCredentials();

        Log::channel('mpesa')->info('Registering C2B URLs', [
            'validation_url'   => $validationUrl,
            'confirmation_url' => $confirmationUrl,
            'response_type'    => $responseType,
            'environment'      => config('mpesa.environment'),
        ]);

        $response = Http::withToken($this->getAccessToken())
            ->post($credentials['base_url'] . '/mpesa/c2b/v1/registerurl', [
                'ShortCode'       => $credentials['shortcode'],
                'ResponseType'    => $responseType,
                'ConfirmationURL' => $confirmationUrl,
                'ValidationURL'   => $validationUrl,
            ]);

        if (! $response->successful() || isset($response->json()['errorCode'])) {
            $error = $response->json('errorMessage')
                ?? $response->json('ResponseDescription')
                ?? 'C2B URL registration failed';

            Log::channel('mpesa')->error('C2B URL registration failed', [
                'status'   => $response->status(),
                'response' => $response->json(),
            ]);

            throw MpesaException::c2bRegistrationFailed($error);
        }

        Log::channel('mpesa')->info('C2B URLs registered successfully', [
            'response' => $response->json(),
        ]);

        return $response->json();
    }

    /**
     * Simulate a C2B payment in sandbox (does not work in production).
     * Useful for testing ValidationURL and ConfirmationURL handlers.
     *
     * @throws MpesaException
     */
    public function simulateC2BPayment(
        string $phoneNumber,
        float $amount,
        string $billRefNumber,
    ): array {
        if (config('mpesa.environment') !== 'sandbox') {
            throw new MpesaException('C2B simulation is only available in sandbox mode.', 'SIMULATION_NOT_ALLOWED');
        }

        $credentials = $this->getActiveCredentials();
        $phone       = PhoneNumber::normalize($phoneNumber);

        $response = Http::withToken($this->getAccessToken())
            ->post($credentials['base_url'] . '/mpesa/c2b/v1/simulate', [
                'ShortCode'     => $credentials['shortcode'],
                'CommandID'     => 'CustomerPayBillOnline',
                'Amount'        => (int) ceil($amount),
                'Msisdn'        => $phone,
                'BillRefNumber' => $billRefNumber,
            ]);

        if (! $response->successful() || isset($response->json()['errorCode'])) {
            $error = $response->json('errorMessage') ?? 'C2B simulation failed';

            Log::channel('mpesa')->error('C2B simulation failed', [
                'status'   => $response->status(),
                'response' => $response->json(),
            ]);

            throw new MpesaException($error, 'SIMULATION_FAILED');
        }

        Log::channel('mpesa')->info('C2B simulation triggered', [
            'bill_ref_number' => $billRefNumber,
            'amount'          => $amount,
            'phone'           => PhoneNumber::mask($phoneNumber),
        ]);

        return $response->json();
    }

    /**
     * Parse an STK Push callback payload from Safaricom into a normalised array.
     *
     * @return array{transaction_reference: string|null, status: string, provider_reference: string|null, failure_reason: string|null, failure_code: string|null}
     */
    public function parseSTKCallbackPayload(array $payload): array
    {
        $callback      = $payload['Body']['stkCallback'] ?? [];
        $resultCode    = $callback['ResultCode'] ?? null;
        $resultDesc    = $callback['ResultDesc'] ?? null;
        $checkoutReqId = $callback['CheckoutRequestID'] ?? null;
        $isSuccess     = $resultCode === 0;

        $mpesaReceiptNumber = null;

        if ($isSuccess) {
            foreach ($callback['CallbackMetadata']['Item'] ?? [] as $item) {
                if (($item['Name'] ?? null) === 'MpesaReceiptNumber') {
                    $mpesaReceiptNumber = $item['Value'] ?? null;
                    break;
                }
            }
        }

        Log::channel('mpesa')->info('STK callback received', [
            'checkout_request_id' => $checkoutReqId,
            'result_code'         => $resultCode,
            'result_desc'         => $resultDesc,
            'receipt'             => $mpesaReceiptNumber,
        ]);

        return [
            'transaction_reference' => $checkoutReqId,
            'status'                => $isSuccess ? 'success' : 'failed',
            'provider_reference'    => $mpesaReceiptNumber,
            'failure_reason'        => $isSuccess ? null : $resultDesc,
            'failure_code'          => $isSuccess ? null : (string) $resultCode,
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
            'trans_id'        => $payload['TransID'] ?? null,
            'amount'          => $payload['TransAmount'] ?? null,
        ]);

        return [
            'transaction_id'      => $payload['TransID'] ?? null,
            'transaction_type'    => $payload['TransactionType'] ?? null,
            'transaction_time'    => $payload['TransTime'] ?? null,
            'amount'              => (float) ($payload['TransAmount'] ?? 0),
            'shortcode'           => $payload['BusinessShortCode'] ?? null,
            'bill_ref_number'     => $payload['BillRefNumber'] ?? null,
            'phone'               => $payload['MSISDN'] ?? null,
            'first_name'          => $payload['FirstName'] ?? null,
            'last_name'           => $payload['LastName'] ?? null,
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
