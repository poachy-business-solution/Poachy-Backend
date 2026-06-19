<?php

namespace App\Services\Central\Marketplace;

use Illuminate\Support\Facades\Log;

class MpesaService
{
    /**
     * Initiate an M-Pesa STK push to the customer's phone.
     *
     * Returns ['checkout_request_id' => string, 'merchant_request_id' => string].
     *
     * TODO: Replace stub with real Safaricom Daraja API call.
     * Required config keys (in config/services.php under 'mpesa'):
     *   - consumer_key, consumer_secret, shortcode, passkey, callback_url
     *
     * @throws \RuntimeException if the STK push request fails.
     */
    public function initiateSTKPush(
        string $phoneNumber,
        float $amount,
        string $accountReference,
        string $transactionDesc,
    ): array {
        // TODO: Implement Daraja API integration.
        // 1. Obtain OAuth token via POST to https://api.safaricom.co.ke/oauth/v1/generate
        // 2. Build STK push payload with BusinessShortCode, Password (base64 of shortcode+passkey+timestamp),
        //    Timestamp, TransactionType, Amount, PartyA (phone), PartyB (shortcode),
        //    PhoneNumber, CallBackURL, AccountReference, TransactionDesc.
        // 3. POST to https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest
        // 4. On success: return ['checkout_request_id' => $res['CheckoutRequestID'], 'merchant_request_id' => $res['MerchantRequestID']]
        // 5. On failure: throw new \RuntimeException($res['errorMessage'] ?? 'STK push failed')

        Log::info('MpesaService::initiateSTKPush (stub)', [
            'phone_number'      => $phoneNumber,
            'amount'            => $amount,
            'account_reference' => $accountReference,
        ]);

        throw new \RuntimeException('M-Pesa integration is not yet configured. Please contact support.');
    }

    /**
     * Validate that a callback originated from Safaricom.
     *
     * TODO: Implement IP allowlist or signature verification before going live.
     * Safaricom IP ranges (as of 2025): 196.201.214.0/24, 196.201.213.0/24
     */
    public function validateCallback(array $payload): bool
    {
        // TODO: Verify request IP against Safaricom allowlist, or validate HMAC signature.
        return true;
    }

    /**
     * Parse an M-Pesa STK callback payload into a normalised webhook format
     * compatible with MarketplacePaymentService::processPaymentWebhook().
     *
     * M-Pesa STK callback structure:
     * {
     *   "Body": {
     *     "stkCallback": {
     *       "MerchantRequestID": "...",
     *       "CheckoutRequestID": "...",
     *       "ResultCode": 0,
     *       "ResultDesc": "The service request is processed successfully.",
     *       "CallbackMetadata": {
     *         "Item": [
     *           {"Name": "Amount",          "Value": 1000.00},
     *           {"Name": "MpesaReceiptNumber", "Value": "LHG31AA5TX"},
     *           {"Name": "TransactionDate", "Value": 20191219102115},
     *           {"Name": "PhoneNumber",     "Value": 254708374149}
     *         ]
     *       }
     *     }
     *   }
     * }
     *
     * @return array{transaction_reference: string, status: string, provider_reference: ?string, failure_reason: ?string, failure_code: ?string}
     */
    public function parseCallbackPayload(array $payload): array
    {
        $callback = $payload['Body']['stkCallback'] ?? [];

        $resultCode      = $callback['ResultCode'] ?? null;
        $resultDesc      = $callback['ResultDesc'] ?? null;
        $checkoutReqId   = $callback['CheckoutRequestID'] ?? null;
        $merchantReqId   = $callback['MerchantRequestID'] ?? null;

        $isSuccess = $resultCode === 0;

        $mpesaReceiptNumber = null;

        if ($isSuccess) {
            $items = $callback['CallbackMetadata']['Item'] ?? [];

            foreach ($items as $item) {
                if (($item['Name'] ?? null) === 'MpesaReceiptNumber') {
                    $mpesaReceiptNumber = $item['Value'] ?? null;
                    break;
                }
            }
        }

        return [
            'transaction_reference' => $checkoutReqId,
            'status'                => $isSuccess ? 'success' : 'failed',
            'provider_reference'    => $mpesaReceiptNumber,
            'failure_reason'        => $isSuccess ? null : $resultDesc,
            'failure_code'          => $isSuccess ? null : (string) $resultCode,
        ];
    }
}
