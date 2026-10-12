<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\DeliversLocalCallbacks;
use App\Services\Shared\Card\Gateways\FakeCardGateway;
use Illuminate\Console\Command;

/**
 * Plays Paystack's part for the fake card driver: marks a fake transaction paid (or failed),
 * then delivers the signed `charge.success` / `charge.failed` webhook to this app.
 *
 *   poachy:simulate-paystack --latest
 *   poachy:simulate-paystack PCH-ORDER-123 --fail
 *   poachy:simulate-paystack --latest --no-webhook     only mark paid (the buyer returns, verify succeeds)
 */
class SimulatePaystackCommand extends Command
{
    use DeliversLocalCallbacks;

    protected $signature = 'poachy:simulate-paystack
        {reference? : Transaction reference}
        {--latest : Use the most recent fake transaction}
        {--fail : The card is declined}
        {--no-webhook : Update the transaction but do not deliver the webhook}';

    protected $description = 'Simulate a Paystack card payment + webhook for the fake driver (never calls Paystack)';

    public function handle(): int
    {
        if (config('paystack.driver') !== 'fake') {
            $this->error('poachy:simulate-paystack only works with PAYSTACK_DRIVER=fake.');

            return self::FAILURE;
        }

        $reference = $this->argument('reference') ?: ($this->option('latest') ? FakeCardGateway::latestReference() : null);
        $tx = $reference ? FakeCardGateway::find($reference) : null;

        if ($tx === null) {
            $this->error('No fake transaction found. Pass a reference or --latest.');

            return self::FAILURE;
        }

        $failed = (bool) $this->option('fail');
        $tx['status'] = $failed ? 'failed' : 'success';
        $tx['paid_at'] = $failed ? null : now()->toIso8601String();
        $tx['channel'] = 'card';
        $tx['gateway_response'] = $failed ? 'Declined' : 'Successful';
        FakeCardGateway::put($reference, $tx);

        if ($this->option('no-webhook')) {
            $this->info("Transaction {$reference} marked {$tx['status']} (no webhook).");

            return self::SUCCESS;
        }

        $body = json_encode([
            'event' => $failed ? 'charge.failed' : 'charge.success',
            'data' => [
                'id' => random_int(1000000, 9999999),
                'status' => $tx['status'],
                'reference' => $reference,
                'amount' => (int) round($tx['amount'] * 100),
                'currency' => $tx['currency'],
                'paid_at' => $tx['paid_at'],
                'channel' => 'card',
                'gateway_response' => $tx['gateway_response'],
                'customer' => ['email' => $tx['email']],
                'metadata' => $tx['metadata'],
            ],
        ]);

        $url = $this->urlOr(config('paystack.webhook_url'), '/api/v1/central/marketplace/payments/paystack/webhook');
        $response = $this->deliverCallback($url, [], ['X-Paystack-Signature' => FakeCardGateway::sign($body)], $body);

        $this->info("Paystack {$reference} → {$tx['status']}: webhook HTTP {$response['status']}");

        return $response['status'] < 400 ? self::SUCCESS : self::FAILURE;
    }
}
