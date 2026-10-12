<?php

namespace App\Services\Shared\Card\Gateways;

use App\Exceptions\CardPaymentException;
use App\Services\Shared\Card\CardGateway;
use App\Services\Shared\Payments\FakeGatewayStore;
use Illuminate\Support\Str;

/**
 * Local stand-in for Paystack: never makes a network call. Transactions are remembered
 * ({@see FakeGatewayStore}, 1 day) as `pending` until `poachy:simulate-paystack` marks them paid or failed and
 * delivers the signed webhook. Webhooks are signed with `paystack.secret_key`, or a fixed
 * local key when none is set, so signature checks run the same way as in production.
 *
 * An email starting with "fail@" makes initialize itself fail, to exercise error paths.
 */
class FakeCardGateway implements CardGateway
{
    public const LOCAL_SECRET = 'sk_test_poachy_fake_local_only';

    public function initialize(string $email, float $amount, string $reference, string $callbackUrl, array $metadata = []): array
    {
        if (str_starts_with($email, 'fail@')) {
            throw new CardPaymentException('Fake Paystack: initialize rejected (test email).', 'PAYSTACK_ERROR');
        }

        if (self::find($reference) !== null) {
            throw new CardPaymentException('Duplicate Transaction Reference', 'PAYSTACK_ERROR');
        }

        $accessCode = 'fake_'.Str::lower(Str::random(12));

        self::put($reference, [
            'reference' => $reference,
            'email' => $email,
            'amount' => round($amount, 2),
            'currency' => config('paystack.currency', 'KES'),
            'status' => 'pending',
            'paid_at' => null,
            'channel' => null,
            'gateway_response' => null,
            'callback_url' => $callbackUrl,
            'metadata' => $metadata,
            'refunds' => [],
        ]);
        FakeGatewayStore::putString('paystack:latest', $reference);

        return [
            'authorization_url' => 'https://checkout.paystack.test/'.$accessCode,
            'access_code' => $accessCode,
            'reference' => $reference,
        ];
    }

    public function verify(string $reference): array
    {
        $tx = self::find($reference) ?? throw new CardPaymentException('Transaction reference not found', 'PAYSTACK_ERROR');

        return [
            'reference' => $tx['reference'],
            'status' => $tx['status'],
            'amount' => (float) $tx['amount'],
            'currency' => $tx['currency'],
            'paid_at' => $tx['paid_at'],
            'channel' => $tx['channel'],
            'gateway_response' => $tx['gateway_response'],
        ];
    }

    public function verifyWebhookSignature(string $rawBody, ?string $signature): bool
    {
        if ($signature === null || $signature === '') {
            return false;
        }

        return hash_equals(self::sign($rawBody), $signature);
    }

    public function refund(string $reference, ?float $amount = null, string $reason = ''): array
    {
        $tx = self::find($reference) ?? throw new CardPaymentException('Transaction reference not found', 'PAYSTACK_ERROR');

        if ($tx['status'] !== 'success') {
            throw new CardPaymentException('Transaction has not been paid', 'PAYSTACK_ERROR');
        }

        $refunded = array_sum(array_column($tx['refunds'], 'amount'));
        $amount ??= $tx['amount'] - $refunded;

        if ($amount <= 0 || $refunded + $amount > $tx['amount'] + 0.001) {
            throw new CardPaymentException('Refund amount is more than what is left on the transaction', 'PAYSTACK_ERROR');
        }

        $refund = ['refund_id' => (string) random_int(100000, 999999), 'status' => 'processed', 'amount' => round($amount, 2)];
        $tx['refunds'][] = $refund + ['reason' => $reason];
        self::put($reference, $tx);

        return $refund;
    }

    /** Sign a webhook body the way Paystack does (HMAC SHA-512 with the secret key). */
    public static function sign(string $rawBody): string
    {
        $secret = (string) config('paystack.secret_key') ?: self::LOCAL_SECRET;

        return hash_hmac('sha512', $rawBody, $secret);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $reference): ?array
    {
        return FakeGatewayStore::get("paystack:tx:{$reference}");
    }

    public static function latestReference(): ?string
    {
        return FakeGatewayStore::getString('paystack:latest');
    }

    /**
     * @param  array<string, mixed>  $tx
     */
    public static function put(string $reference, array $tx): void
    {
        FakeGatewayStore::put("paystack:tx:{$reference}", $tx);
    }
}
