<?php

namespace App\Services\Shared\Card;

use App\Exceptions\CardPaymentException;

/**
 * Card payments (Paystack). Selected by `config('paystack.driver')`: `paystack` calls the real
 * API, `fake` records transactions locally and never leaves the app (webhooks are then
 * delivered with `php artisan poachy:simulate-paystack`).
 *
 * Amounts are in shillings; drivers convert to Paystack's subunits (cents).
 */
interface CardGateway
{
    /**
     * Start a transaction; the buyer is sent to `authorization_url` to pay.
     *
     * @param  array<string, mixed>  $metadata
     * @return array{authorization_url: string, access_code: string, reference: string}
     *
     * @throws CardPaymentException
     */
    public function initialize(string $email, float $amount, string $reference, string $callbackUrl, array $metadata = []): array;

    /**
     * Look a transaction up by reference — the source of truth before giving value.
     *
     * @return array{reference: string, status: string, amount: float, currency: string, paid_at: string|null, channel: string|null, gateway_response: string|null}
     *
     * @throws CardPaymentException
     */
    public function verify(string $reference): array;

    /** True when the webhook body was signed with our secret key. */
    public function verifyWebhookSignature(string $rawBody, ?string $signature): bool;

    /**
     * Refund a transaction in full, or part of it when `$amount` is given.
     *
     * @return array{refund_id: string, status: string, amount: float}
     *
     * @throws CardPaymentException
     */
    public function refund(string $reference, ?float $amount = null, string $reason = ''): array;
}
