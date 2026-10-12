<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\DeliversLocalCallbacks;
use App\Helpers\PhoneNumber;
use App\Services\Shared\Mpesa\Gateways\FakeMpesaGateway;
use Illuminate\Console\Command;

/**
 * Plays Safaricom's part for the fake M-Pesa driver: builds the callback Daraja would send and
 * delivers it to this app's own callback route.
 *
 *   poachy:simulate-mpesa stk --latest                 STK prompt paid
 *   poachy:simulate-mpesa stk ws_CO_FAKE_… --fail      STK prompt cancelled (code 1032)
 *   poachy:simulate-mpesa c2b --account=POA00001 --amount=500 --phone=0712345678
 *   poachy:simulate-mpesa b2c --latest [--fail]        payout result
 *   poachy:simulate-mpesa reversal --latest [--fail]   reversal result
 */
class SimulateMpesaCommand extends Command
{
    use DeliversLocalCallbacks;

    protected $signature = 'poachy:simulate-mpesa
        {type : stk | c2b | b2c | reversal}
        {id? : CheckoutRequestID (stk) or ConversationID (b2c / reversal)}
        {--latest : Use the most recent fake request of this type}
        {--fail : Deliver a failure result instead of success}
        {--code= : Result code for a failure (default 1032 for STK, 2001 otherwise)}
        {--account= : C2B account number (BillRefNumber), e.g. POA00001}
        {--amount= : C2B amount in shillings}
        {--phone=254708374149 : C2B payer phone}
        {--name=Test Payer : C2B payer first name}
        {--trans-id= : C2B transaction id (defaults to a random fake code)}
        {--skip-validation : C2B: go straight to confirmation}';

    protected $description = 'Simulate an M-Pesa callback for the fake driver (never calls Safaricom)';

    public function handle(): int
    {
        if (config('mpesa.driver') !== 'fake') {
            $this->error('poachy:simulate-mpesa only works with MPESA_DRIVER=fake.');

            return self::FAILURE;
        }

        return match ($this->argument('type')) {
            'stk' => $this->stk(),
            'c2b' => $this->c2b(),
            'b2c', 'reversal' => $this->result((string) $this->argument('type')),
            default => $this->invalidType(),
        };
    }

    private function stk(): int
    {
        $id = $this->requestId('stk');
        $request = $id ? FakeMpesaGateway::find('stk', $id) : null;

        if ($request === null) {
            $this->error('No fake STK request found. Pass a CheckoutRequestID or --latest.');

            return self::FAILURE;
        }

        $failed = (bool) $this->option('fail');
        $code = $failed ? (int) ($this->option('code') ?: 1032) : 0;
        $desc = $failed ? 'Request cancelled by user' : 'The service request is processed successfully.';
        $receipt = FakeMpesaGateway::transactionId();

        $callback = [
            'MerchantRequestID' => $request['merchant_request_id'],
            'CheckoutRequestID' => $request['checkout_request_id'],
            'ResultCode' => $code,
            'ResultDesc' => $desc,
        ];

        if (! $failed) {
            $callback['CallbackMetadata'] = ['Item' => [
                ['Name' => 'Amount', 'Value' => $request['amount']],
                ['Name' => 'MpesaReceiptNumber', 'Value' => $receipt],
                ['Name' => 'TransactionDate', 'Value' => (int) now()->format('YmdHis')],
                ['Name' => 'PhoneNumber', 'Value' => (int) $request['phone']],
            ]];
        }

        FakeMpesaGateway::update('stk', $id, ['result_code' => $code, 'result_desc' => $desc]);

        $response = $this->deliverCallback($request['callback_url'], ['Body' => ['stkCallback' => $callback]]);

        $this->info(sprintf('STK %s → %s: HTTP %d%s', $id, $failed ? "failed ({$code})" : 'paid', $response['status'], $failed ? '' : " · receipt {$receipt}"));

        return $response['status'] < 400 ? self::SUCCESS : self::FAILURE;
    }

    private function c2b(): int
    {
        $account = (string) $this->option('account');
        $amount = (float) $this->option('amount');

        if ($account === '' || $amount <= 0) {
            $this->error('C2B needs --account and a positive --amount.');

            return self::FAILURE;
        }

        $payload = [
            'TransactionType' => 'Pay Bill',
            'TransID' => $this->option('trans-id') ?: FakeMpesaGateway::transactionId(),
            'TransTime' => now()->format('YmdHis'),
            'TransAmount' => number_format($amount, 2, '.', ''),
            'BusinessShortCode' => config('mpesa.'.config('mpesa.environment').'.shortcode'),
            'BillRefNumber' => $account,
            'InvoiceNumber' => '',
            'OrgAccountBalance' => '',
            'ThirdPartyTransID' => '',
            'MSISDN' => PhoneNumber::normalize((string) $this->option('phone')),
            'FirstName' => (string) $this->option('name'),
        ];

        if (! $this->option('skip-validation')) {
            $validation = $this->deliverCallback($this->urlOr(config('mpesa.c2b_validation_url'), '/api/v1/central/mpesa/c2b/validate'), $payload);
            $resultCode = (string) ($validation['body']['ResultCode'] ?? '');
            $this->line("Validation: HTTP {$validation['status']} · ResultCode {$resultCode} · ".($validation['body']['ResultDesc'] ?? ''));

            if ($resultCode !== '0') {
                $this->warn('Rejected by validation — Safaricom would not complete this payment.');

                return self::SUCCESS;
            }
        }

        $payload['OrgAccountBalance'] = '0.00';
        $confirmation = $this->deliverCallback($this->urlOr(config('mpesa.c2b_confirmation_url'), '/api/v1/central/mpesa/c2b/confirm'), $payload);
        $this->info("Confirmation {$payload['TransID']}: HTTP {$confirmation['status']}");

        return $confirmation['status'] < 400 ? self::SUCCESS : self::FAILURE;
    }

    private function result(string $kind): int
    {
        $id = $this->requestId($kind);
        $request = $id ? FakeMpesaGateway::find($kind, $id) : null;

        if ($request === null) {
            $this->error("No fake {$kind} request found. Pass a ConversationID or --latest.");

            return self::FAILURE;
        }

        $failed = (bool) $this->option('fail');
        $code = $failed ? (int) ($this->option('code') ?: 2001) : 0;
        $transactionId = FakeMpesaGateway::transactionId();

        $result = [
            'ResultType' => 0,
            'ResultCode' => $code,
            'ResultDesc' => $failed ? 'The initiator information is invalid.' : 'The service request is processed successfully.',
            'OriginatorConversationID' => $request['originator_conversation_id'],
            'ConversationID' => $request['conversation_id'],
            'TransactionID' => $transactionId,
        ];

        if (! $failed && $kind === 'b2c') {
            $result['ResultParameters'] = ['ResultParameter' => [
                ['Key' => 'TransactionAmount', 'Value' => $request['amount']],
                ['Key' => 'TransactionReceipt', 'Value' => $transactionId],
                ['Key' => 'ReceiverPartyPublicName', 'Value' => $request['phone'].' - Fake Receiver'],
                ['Key' => 'TransactionCompletedDateTime', 'Value' => now()->format('d.m.Y H:i:s')],
                ['Key' => 'B2CUtilityAccountAvailableFunds', 'Value' => 0],
            ]];
        }

        if (! $failed && $kind === 'reversal') {
            $result['ResultParameters'] = ['ResultParameter' => [
                ['Key' => 'Amount', 'Value' => $request['amount']],
                ['Key' => 'OriginalTransactionID', 'Value' => $request['transaction_id']],
            ]];
        }

        FakeMpesaGateway::update($kind, $id, ['result_code' => $code]);

        $response = $this->deliverCallback($request['result_url'], ['Result' => $result]);
        $this->info(sprintf('%s %s → %s: HTTP %d', strtoupper($kind), $id, $failed ? "failed ({$code})" : 'completed', $response['status']));

        return $response['status'] < 400 ? self::SUCCESS : self::FAILURE;
    }

    private function requestId(string $kind): ?string
    {
        return $this->argument('id') ?: ($this->option('latest') ? FakeMpesaGateway::latestId($kind) : null);
    }

    private function invalidType(): int
    {
        $this->error('Type must be one of: stk, c2b, b2c, reversal.');

        return self::FAILURE;
    }
}
