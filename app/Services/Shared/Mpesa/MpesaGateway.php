<?php

namespace App\Services\Shared\Mpesa;

use App\Exceptions\MpesaException;

/**
 * Talks to M-Pesa (Daraja). Selected by `config('mpesa.driver')`:
 * `daraja` calls Safaricom, `fake` records requests locally and never leaves the app
 * (callbacks are then delivered with `php artisan poachy:simulate-mpesa`).
 *
 * Phone numbers arrive already normalised (2547XXXXXXXX); amounts are whole shillings.
 */
interface MpesaGateway
{
    /**
     * Send an STK push (M-Pesa Express) prompt.
     *
     * @return array{checkout_request_id: string, merchant_request_id: string}
     *
     * @throws MpesaException
     */
    public function stkPush(string $phone, float $amount, string $accountReference, string $transactionDesc, string $callbackUrl): array;

    /**
     * Ask for the result of an STK push (STK query).
     *
     * @return array{result_code: int|null, result_desc: string|null}
     *
     * @throws MpesaException
     */
    public function stkStatus(string $checkoutRequestId): array;

    /**
     * Register the C2B (Paybill) validation and confirmation URLs.
     *
     * @return array<string, mixed>
     *
     * @throws MpesaException
     */
    public function registerC2BUrls(string $validationUrl, string $confirmationUrl, string $responseType = 'Cancelled'): array;

    /**
     * Simulate a customer Paybill payment (sandbox / fake only).
     *
     * @return array<string, mixed>
     *
     * @throws MpesaException
     */
    public function simulateC2B(string $phone, float $amount, string $billRefNumber): array;

    /**
     * Pay money out to a phone (B2C). The result arrives later at `$resultUrl`.
     *
     * @return array{conversation_id: string, originator_conversation_id: string}
     *
     * @throws MpesaException
     */
    public function b2c(string $phone, float $amount, string $occasion, string $remarks, string $resultUrl, string $timeoutUrl): array;

    /**
     * Reverse a completed M-Pesa transaction. The result arrives later at `$resultUrl`.
     *
     * @return array{conversation_id: string, originator_conversation_id: string}
     *
     * @throws MpesaException
     */
    public function reverse(string $transactionId, float $amount, string $remarks, string $resultUrl, string $timeoutUrl): array;
}
