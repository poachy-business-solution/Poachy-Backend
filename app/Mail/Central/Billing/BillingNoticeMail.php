<?php

namespace App\Mail\Central\Billing;

use App\Mail\PoachyMail;

/**
 * A billing email to a shop. Each kind has its own template under resources/views/mail/billing,
 * and every one carries the Paybill box (business number, account number and, when given, a pay link).
 *
 * Kinds and their `$data`:
 *   welcome       ownerName, workspace, trialEndsOn, dayPrice, monthCap
 *   low_balance   balance, daysCovered
 *   grace         balance
 *   limited       balance
 *   restored      balance
 *   trial_ending  daysLeft, trialEndsOn, dayPrice, monthCap
 *   payment_link  validDays, amount
 */
class BillingNoticeMail extends PoachyMail
{
    public const KINDS = ['welcome', 'low_balance', 'grace', 'limited', 'restored', 'trial_ending', 'payment_link'];

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $kind,
        public string $shopName,
        public string $accountNumber,
        public ?string $payUrl = null,
        public array $data = [],
    ) {
        if (! in_array($kind, self::KINDS, true)) {
            throw new \InvalidArgumentException("Unknown billing notice: {$kind}");
        }
        $this->onQueue('default');
    }

    protected function subjectLine(): string
    {
        return match ($this->kind) {
            'welcome' => "Welcome to Poachy — {$this->shopName} is ready",
            'low_balance' => 'Top up soon: Poachy balance is running low',
            'grace' => 'Your Poachy balance has run out',
            'limited' => 'Poachy is in limited mode — selling still works',
            'restored' => 'Poachy is fully back on',
            'trial_ending' => (int) $this->data['daysLeft'] === 1
                ? 'Your Poachy free trial ends tomorrow'
                : "Your Poachy free trial ends in {$this->data['daysLeft']} days",
            'payment_link' => 'Your Poachy payment link',
        };
    }

    protected function markdownView(): string
    {
        return 'mail.billing.'.str_replace('_', '-', $this->kind);
    }
}
