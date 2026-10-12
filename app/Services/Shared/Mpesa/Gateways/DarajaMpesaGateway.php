<?php

namespace App\Services\Shared\Mpesa\Gateways;

use App\Exceptions\MpesaException;
use App\Helpers\PhoneNumber;
use App\Services\Shared\Mpesa\MpesaGateway;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The real Daraja driver. Only used when `MPESA_DRIVER=daraja` and live or sandbox keys are set.
 */
class DarajaMpesaGateway implements MpesaGateway
{
    /**
     * @return array{base_url: string, consumer_key: string, consumer_secret: string, shortcode: string, passkey: string, initiator_name?: string, security_credential?: string}
     */
    private function credentials(): array
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
    public function accessToken(): string
    {
        $env = config('mpesa.environment', 'sandbox');
        $cacheKey = "mpesa_access_token_{$env}";
        $credentials = $this->credentials();

        return Cache::remember($cacheKey, 3480, function () use ($credentials, $env) {
            $response = Http::withBasicAuth(
                $credentials['consumer_key'],
                $credentials['consumer_secret'],
            )->get($credentials['base_url'].'/oauth/v1/generate', [
                'grant_type' => 'client_credentials',
            ]);

            if (! $response->successful() || ! $response->json('access_token')) {
                Log::channel('mpesa')->error('Daraja OAuth token request failed', [
                    'environment' => $env,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw MpesaException::oauthFailed($response->body());
            }

            Log::channel('mpesa')->debug('Daraja OAuth token obtained', ['environment' => $env]);

            return $response->json('access_token');
        });
    }

    public function stkPush(string $phone, float $amount, string $accountReference, string $transactionDesc, string $callbackUrl): array
    {
        $credentials = $this->credentials();
        $shortcode = $credentials['shortcode'];
        $timestamp = now()->format('YmdHis');

        $response = Http::withToken($this->accessToken())
            ->post($credentials['base_url'].'/mpesa/stkpush/v1/processrequest', [
                'BusinessShortCode' => $shortcode,
                'Password' => base64_encode($shortcode.$credentials['passkey'].$timestamp),
                'Timestamp' => $timestamp,
                'TransactionType' => 'CustomerPayBillOnline',
                'Amount' => (int) ceil($amount), // M-Pesa requires integer amounts
                'PartyA' => $phone,
                'PartyB' => $shortcode,
                'PhoneNumber' => $phone,
                'CallBackURL' => $callbackUrl,
                'AccountReference' => substr($accountReference, 0, 12), // Daraja max 12 chars
                'TransactionDesc' => substr($transactionDesc, 0, 13),  // Daraja max 13 chars
            ]);

        if ($this->failed($response)) {
            $error = $response->json('errorMessage')
                ?? $response->json('ResponseDescription')
                ?? 'STK push failed';

            Log::channel('mpesa')->error('STK push failed', [
                'status' => $response->status(),
                'response' => $response->json(),
                'phone' => PhoneNumber::mask($phone),
            ]);

            throw MpesaException::stkPushFailed($error, [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);
        }

        return [
            'checkout_request_id' => $response->json('CheckoutRequestID'),
            'merchant_request_id' => $response->json('MerchantRequestID'),
        ];
    }

    public function stkStatus(string $checkoutRequestId): array
    {
        $credentials = $this->credentials();
        $shortcode = $credentials['shortcode'];
        $timestamp = now()->format('YmdHis');

        $response = Http::withToken($this->accessToken())
            ->post($credentials['base_url'].'/mpesa/stkpushquery/v1/query', [
                'BusinessShortCode' => $shortcode,
                'Password' => base64_encode($shortcode.$credentials['passkey'].$timestamp),
                'Timestamp' => $timestamp,
                'CheckoutRequestID' => $checkoutRequestId,
            ]);

        if ($this->failed($response)) {
            throw new MpesaException($response->json('errorMessage') ?? 'STK status query failed', 'STK_QUERY_FAILED', [
                'status' => $response->status(),
            ]);
        }

        $code = $response->json('ResultCode');

        return [
            'result_code' => $code === null ? null : (int) $code,
            'result_desc' => $response->json('ResultDesc'),
        ];
    }

    public function registerC2BUrls(string $validationUrl, string $confirmationUrl, string $responseType = 'Cancelled'): array
    {
        $credentials = $this->credentials();

        $response = Http::withToken($this->accessToken())
            ->post($credentials['base_url'].'/mpesa/c2b/v1/registerurl', [
                'ShortCode' => $credentials['shortcode'],
                'ResponseType' => $responseType,
                'ConfirmationURL' => $confirmationUrl,
                'ValidationURL' => $validationUrl,
            ]);

        if ($this->failed($response)) {
            $error = $response->json('errorMessage')
                ?? $response->json('ResponseDescription')
                ?? 'C2B URL registration failed';

            Log::channel('mpesa')->error('C2B URL registration failed', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            throw MpesaException::c2bRegistrationFailed($error);
        }

        return $response->json();
    }

    public function simulateC2B(string $phone, float $amount, string $billRefNumber): array
    {
        if (config('mpesa.environment') !== 'sandbox') {
            throw new MpesaException('C2B simulation is only available in sandbox mode.', 'SIMULATION_NOT_ALLOWED');
        }

        $credentials = $this->credentials();

        $response = Http::withToken($this->accessToken())
            ->post($credentials['base_url'].'/mpesa/c2b/v1/simulate', [
                'ShortCode' => $credentials['shortcode'],
                'CommandID' => 'CustomerPayBillOnline',
                'Amount' => (int) ceil($amount),
                'Msisdn' => $phone,
                'BillRefNumber' => $billRefNumber,
            ]);

        if ($this->failed($response)) {
            $error = $response->json('errorMessage') ?? 'C2B simulation failed';

            Log::channel('mpesa')->error('C2B simulation failed', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            throw new MpesaException($error, 'SIMULATION_FAILED');
        }

        return $response->json();
    }

    public function b2c(string $phone, float $amount, string $occasion, string $remarks, string $resultUrl, string $timeoutUrl): array
    {
        $credentials = $this->credentials();

        $response = Http::withToken($this->accessToken())
            ->post($credentials['base_url'].'/mpesa/b2c/v3/paymentrequest', [
                'OriginatorConversationID' => (string) str()->uuid(),
                'InitiatorName' => $credentials['initiator_name'] ?? '',
                'SecurityCredential' => $credentials['security_credential'] ?? '',
                'CommandID' => 'BusinessPayment',
                'Amount' => (int) floor($amount),
                'PartyA' => $credentials['b2c_shortcode'] ?? $credentials['shortcode'],
                'PartyB' => $phone,
                'Remarks' => substr($remarks, 0, 100),
                'QueueTimeOutURL' => $timeoutUrl,
                'ResultURL' => $resultUrl,
                'Occassion' => substr($occasion, 0, 100), // Daraja's spelling
            ]);

        if ($this->failed($response)) {
            throw new MpesaException($response->json('errorMessage') ?? 'B2C request failed', 'B2C_FAILED', [
                'status' => $response->status(),
            ]);
        }

        return [
            'conversation_id' => $response->json('ConversationID'),
            'originator_conversation_id' => $response->json('OriginatorConversationID'),
        ];
    }

    public function reverse(string $transactionId, float $amount, string $remarks, string $resultUrl, string $timeoutUrl): array
    {
        $credentials = $this->credentials();

        $response = Http::withToken($this->accessToken())
            ->post($credentials['base_url'].'/mpesa/reversal/v1/request', [
                'Initiator' => $credentials['initiator_name'] ?? '',
                'SecurityCredential' => $credentials['security_credential'] ?? '',
                'CommandID' => 'TransactionReversal',
                'TransactionID' => $transactionId,
                'Amount' => (int) ceil($amount),
                'ReceiverParty' => $credentials['shortcode'],
                'RecieverIdentifierType' => '11', // Daraja's spelling
                'ResultURL' => $resultUrl,
                'QueueTimeOutURL' => $timeoutUrl,
                'Remarks' => substr($remarks, 0, 100),
                'Occasion' => '',
            ]);

        if ($this->failed($response)) {
            throw new MpesaException($response->json('errorMessage') ?? 'Reversal request failed', 'REVERSAL_FAILED', [
                'status' => $response->status(),
            ]);
        }

        return [
            'conversation_id' => $response->json('ConversationID'),
            'originator_conversation_id' => $response->json('OriginatorConversationID'),
        ];
    }

    private function failed(Response $response): bool
    {
        return ! $response->successful() || isset($response->json()['errorCode']);
    }
}
