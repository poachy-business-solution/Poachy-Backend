<?php

namespace App\Services\Shared\Card\Gateways;

use App\Exceptions\CardPaymentException;
use App\Services\Shared\Card\CardGateway;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The real Paystack driver. Only used when `PAYSTACK_DRIVER=paystack` and keys are set.
 */
class PaystackCardGateway implements CardGateway
{
    public function initialize(string $email, float $amount, string $reference, string $callbackUrl, array $metadata = []): array
    {
        $response = $this->http()->post('/transaction/initialize', [
            'email' => $email,
            'amount' => self::toSubunits($amount),
            'currency' => config('paystack.currency', 'KES'),
            'reference' => $reference,
            'callback_url' => $callbackUrl,
            'metadata' => $metadata,
        ]);

        $data = $this->data($response, 'Card payment could not be started.');

        return [
            'authorization_url' => $data['authorization_url'],
            'access_code' => $data['access_code'],
            'reference' => $data['reference'],
        ];
    }

    public function verify(string $reference): array
    {
        $response = $this->http()->get('/transaction/verify/'.rawurlencode($reference));
        $data = $this->data($response, 'Card payment could not be verified.');

        return [
            'reference' => $data['reference'],
            'status' => $data['status'],
            'amount' => ((int) $data['amount']) / 100,
            'currency' => $data['currency'],
            'paid_at' => $data['paid_at'] ?? null,
            'channel' => $data['channel'] ?? null,
            'gateway_response' => $data['gateway_response'] ?? null,
        ];
    }

    public function verifyWebhookSignature(string $rawBody, ?string $signature): bool
    {
        $secret = (string) config('paystack.secret_key');

        if ($secret === '' || $signature === null || $signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha512', $rawBody, $secret), $signature);
    }

    public function refund(string $reference, ?float $amount = null, string $reason = ''): array
    {
        $payload = ['transaction' => $reference, 'merchant_note' => $reason];

        if ($amount !== null) {
            $payload['amount'] = self::toSubunits($amount);
        }

        $data = $this->data($this->http()->post('/refund', $payload), 'Card refund failed.');

        return [
            'refund_id' => (string) $data['id'],
            'status' => $data['status'],
            'amount' => ((int) $data['amount']) / 100,
        ];
    }

    public static function toSubunits(float $amount): int
    {
        return (int) round($amount * 100);
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(config('paystack.base_url'))
            ->withToken((string) config('paystack.secret_key'))
            ->acceptJson()
            ->timeout(20);
    }

    /**
     * @return array<string, mixed>
     */
    private function data(Response $response, string $fallbackMessage): array
    {
        if (! $response->successful() || $response->json('status') !== true) {
            Log::channel('mpesa')->error('Paystack request failed', [
                'status' => $response->status(),
                'message' => $response->json('message'),
            ]);

            throw new CardPaymentException($response->json('message') ?? $fallbackMessage, 'PAYSTACK_ERROR', [
                'status' => $response->status(),
            ]);
        }

        return $response->json('data') ?? [];
    }
}
