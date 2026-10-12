<?php

namespace App\Services\Shared\Mpesa\Gateways;

use App\Exceptions\MpesaException;
use App\Services\Shared\Mpesa\MpesaGateway;
use App\Services\Shared\Payments\FakeGatewayStore;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Local stand-in for Daraja: never makes a network call. Each request is remembered
 * ({@see FakeGatewayStore}, 1 day) so `poachy:simulate-mpesa` can later deliver the callback Safaricom would send.
 *
 * The phone 0700 999 999 makes the request itself fail, to exercise error paths.
 */
class FakeMpesaGateway implements MpesaGateway
{
    public const FAILING_PHONE = '254700999999';

    public function stkPush(string $phone, float $amount, string $accountReference, string $transactionDesc, string $callbackUrl): array
    {
        $this->failIfAsked($phone, 'STK_PUSH_FAILED');

        $checkoutRequestId = 'ws_CO_FAKE_'.Str::upper(Str::random(16));
        $merchantRequestId = 'FAKE-'.Str::upper(Str::random(10));

        $this->remember('stk', $checkoutRequestId, [
            'checkout_request_id' => $checkoutRequestId,
            'merchant_request_id' => $merchantRequestId,
            'phone' => $phone,
            'amount' => (int) ceil($amount),
            'account_reference' => substr($accountReference, 0, 12),
            'transaction_desc' => substr($transactionDesc, 0, 13),
            'callback_url' => $callbackUrl,
        ]);

        return ['checkout_request_id' => $checkoutRequestId, 'merchant_request_id' => $merchantRequestId];
    }

    public function stkStatus(string $checkoutRequestId): array
    {
        $request = self::find('stk', $checkoutRequestId);

        if ($request === null) {
            throw new MpesaException('Unknown checkout request.', 'STK_QUERY_FAILED');
        }

        return [
            'result_code' => $request['result_code'] ?? null,
            'result_desc' => $request['result_desc'] ?? 'The transaction is being processed',
        ];
    }

    public function registerC2BUrls(string $validationUrl, string $confirmationUrl, string $responseType = 'Cancelled'): array
    {
        FakeGatewayStore::put('mpesa:c2b_urls', compact('validationUrl', 'confirmationUrl', 'responseType'));

        return ['OriginatorCoversationID' => 'FAKE', 'ResponseCode' => '0', 'ResponseDescription' => 'Success'];
    }

    public function simulateC2B(string $phone, float $amount, string $billRefNumber): array
    {
        $transactionId = self::transactionId();

        $this->remember('c2b', $transactionId, [
            'transaction_id' => $transactionId,
            'phone' => $phone,
            'amount' => (int) ceil($amount),
            'bill_ref_number' => $billRefNumber,
        ]);

        return ['ResponseCode' => '0', 'ResponseDescription' => 'Accept the service request successfully.', 'TransID' => $transactionId];
    }

    public function b2c(string $phone, float $amount, string $occasion, string $remarks, string $resultUrl, string $timeoutUrl): array
    {
        $this->failIfAsked($phone, 'B2C_FAILED');

        return $this->rememberResultRequest('b2c', [
            'phone' => $phone,
            'amount' => (int) floor($amount),
            'occasion' => $occasion,
            'remarks' => $remarks,
            'result_url' => $resultUrl,
            'timeout_url' => $timeoutUrl,
        ]);
    }

    public function reverse(string $transactionId, float $amount, string $remarks, string $resultUrl, string $timeoutUrl): array
    {
        return $this->rememberResultRequest('reversal', [
            'transaction_id' => $transactionId,
            'amount' => (int) ceil($amount),
            'remarks' => $remarks,
            'result_url' => $resultUrl,
            'timeout_url' => $timeoutUrl,
        ]);
    }

    /**
     * A remembered request, or null.
     *
     * @return array<string, mixed>|null
     */
    public static function find(string $kind, string $id): ?array
    {
        return FakeGatewayStore::get("mpesa:{$kind}:{$id}");
    }

    /** Id of the most recent request of a kind, or null. */
    public static function latestId(string $kind): ?string
    {
        return FakeGatewayStore::getString("mpesa:{$kind}:latest");
    }

    /**
     * Update a remembered request (e.g. record the simulated result for STK status queries).
     *
     * @param  array<string, mixed>  $changes
     */
    public static function update(string $kind, string $id, array $changes): void
    {
        $request = self::find($kind, $id);

        if ($request !== null) {
            FakeGatewayStore::put("mpesa:{$kind}:{$id}", array_merge($request, $changes));
        }
    }

    /** A receipt-like M-Pesa transaction code, e.g. "TJK4FAKE12". */
    public static function transactionId(): string
    {
        return 'FK'.Str::upper(Str::random(8));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{conversation_id: string, originator_conversation_id: string}
     */
    private function rememberResultRequest(string $kind, array $data): array
    {
        $conversationId = 'AG_FAKE_'.Str::upper(Str::random(12));
        $originatorConversationId = (string) Str::uuid();

        $this->remember($kind, $conversationId, $data + [
            'conversation_id' => $conversationId,
            'originator_conversation_id' => $originatorConversationId,
        ]);

        return ['conversation_id' => $conversationId, 'originator_conversation_id' => $originatorConversationId];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function remember(string $kind, string $id, array $data): void
    {
        FakeGatewayStore::put("mpesa:{$kind}:{$id}", $data + ['kind' => $kind, 'created_at' => now()->toIso8601String()]);
        FakeGatewayStore::putString("mpesa:{$kind}:latest", $id);

        Log::channel('mpesa')->info("Fake M-Pesa {$kind} request recorded", ['id' => $id]);
    }

    private function failIfAsked(string $phone, string $code): void
    {
        if ($phone === self::FAILING_PHONE) {
            throw new MpesaException('Fake M-Pesa: request rejected (test phone).', $code);
        }
    }
}
