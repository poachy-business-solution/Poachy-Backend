<?php

namespace App\Services\Central\Admin;

use App\Mail\Central\Billing\BillingNoticeMail;
use App\Mail\Central\Billing\BillingReceiptMail;
use App\Mail\Central\Signup\SignupCodeMail;
use App\Mail\PoachyMail;
use Illuminate\Support\Facades\URL;

/**
 * The transactional emails Poachy sends, rendered with sample data so support can see
 * what a shop receives without sending anything. Read-only: nothing is queued or logged.
 */
class EmailTemplatePreview
{
    /** @var array<string, array{name: string, description: string}> */
    public const TEMPLATES = [
        'welcome' => ['name' => 'Welcome', 'description' => 'Sent when a new shop is ready (self sign-up or opened by staff).'],
        'receipt' => ['name' => 'Top-up receipt', 'description' => 'Sent after every Paybill, STK or pay-link payment.'],
        'low_balance' => ['name' => 'Low balance', 'description' => 'Fewer than three trading days left on the balance.'],
        'grace' => ['name' => 'Grace day', 'description' => 'Balance ran out; one day of grace before limited mode.'],
        'limited' => ['name' => 'Limited mode', 'description' => 'Selling still works; reports, receiving and admin screens are blocked.'],
        'restored' => ['name' => 'Back to normal', 'description' => 'A top-up brought the shop out of limited mode.'],
        'trial_ending' => ['name' => 'Trial ending', 'description' => 'Reminders 7, 3 and 1 days before the free trial ends.'],
        'payment_link' => ['name' => 'Payment link', 'description' => 'A one-time link to pay the balance (sent by support or with notices).'],
        'signup_code' => ['name' => 'Sign-up code', 'description' => 'The six-digit email code during sign-up.'],
    ];

    /**
     * @return list<array{key: string, name: string, description: string}>
     */
    public function list(): array
    {
        return array_values(array_map(fn (string $key, array $t) => ['key' => $key] + $t, array_keys(self::TEMPLATES), self::TEMPLATES));
    }

    /**
     * @return array{key: string, name: string, subject: string, html: string}|null
     */
    public function render(string $key): ?array
    {
        $mail = $this->sample($key);
        if ($mail === null) {
            return null;
        }

        return [
            'key' => $key,
            'name' => self::TEMPLATES[$key]['name'],
            'subject' => $mail->subjectForLog(),
            'html' => $mail->render(),
        ];
    }

    protected function sample(string $key): ?PoachyMail
    {
        $shop = 'Mama Njeri Duka';
        $account = 'POA00042';
        $payUrl = rtrim((string) config('app.frontend_url', URL::to('/')), '/').'/pay/sample-link';
        $common = ['dayPrice' => 100, 'monthCap' => 2500, 'balance' => 180, 'daysCovered' => 1, 'trialEndsOn' => now()->addDays(3)->format('j M Y')];

        return match ($key) {
            'welcome' => new BillingNoticeMail('welcome', $shop, $account, $payUrl, $common + ['ownerName' => 'Njeri Kamau', 'workspace' => 'mama-njeri', 'byAdmin' => false]),
            'receipt' => new BillingReceiptMail($shop, 500.0, 'SAMPLE1234', 680.0, $account, now()->format('j M Y H:i'), 'M-Pesa Paybill', $payUrl),
            'low_balance' => new BillingNoticeMail('low_balance', $shop, $account, $payUrl, $common),
            'grace' => new BillingNoticeMail('grace', $shop, $account, $payUrl, $common + ['balance' => 0]),
            'limited' => new BillingNoticeMail('limited', $shop, $account, $payUrl, $common + ['balance' => -50]),
            'restored' => new BillingNoticeMail('restored', $shop, $account, $payUrl, $common + ['balance' => 950]),
            'trial_ending' => new BillingNoticeMail('trial_ending', $shop, $account, $payUrl, $common + ['daysLeft' => 3]),
            'payment_link' => new BillingNoticeMail('payment_link', $shop, $account, $payUrl, $common + ['amount' => 500, 'validDays' => 7]),
            'signup_code' => new SignupCodeMail('Njeri Kamau', '123456', 15),
            default => null,
        };
    }
}
