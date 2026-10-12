<?php

namespace App\Services\Central\Billing;

use App\Enums\Central\BillingStatus;
use App\Mail\Shared\PlainMessageMail;
use App\Models\BillingAccount;
use App\Models\BusinessDetail;
use App\Models\Tenant;
use App\Services\Shared\Messaging\Messenger;
use Carbon\CarbonImmutable;

/**
 * Email + SMS notices for billing state changes and low balance.
 * (Item 19 replaces the wording with dedicated templates and adds pay links.)
 */
class BillingNotices
{
    public function __construct(private Messenger $messenger) {}

    public function statusChanged(BillingAccount $account, ?BillingStatus $from, BillingStatus $to): void
    {
        [$shop, $poa] = $this->shop($account->tenant_id);

        match ($to) {
            BillingStatus::Grace => $this->send($account, 'billing_grace',
                'Your Poachy balance has run out',
                ["{$shop}'s Poachy balance is KSh ".number_format((float) $account->balance, 2).'.', 'Everything keeps working for one more day. After that, reports, receiving stock, transfers and settings pause until you top up — selling never stops.'],
                "Poachy: {$shop}'s balance has run out. Top up today to keep full access: Paybill {$this->paybill()}, account {$poa}.", $poa),
            BillingStatus::Limited => $this->send($account, 'billing_limited',
                'Poachy is in limited mode',
                ["{$shop} is now in limited mode because the Poachy balance is KSh ".number_format((float) $account->balance, 2).'.', 'You can still sell and sync. Reports, receiving stock, transfers and settings come back the moment you top up.'],
                "Poachy: {$shop} is in limited mode. Selling still works. Top up to restore everything: Paybill {$this->paybill()}, account {$poa}.", $poa),
            BillingStatus::Active => in_array($from, [BillingStatus::Grace, BillingStatus::Limited], true)
                ? $this->send($account, 'billing_restored', 'Poachy is fully back on', ["Thanks — {$shop}'s top-up arrived and everything is unlocked again."], null, null)
                : null,
            default => null,
        };
    }

    /** At most one low-balance notice per Nairobi day, only in daytime. */
    public function lowBalance(BillingAccount $account, int $daysCovered, CarbonImmutable $now): void
    {
        $local = $now->setTimezone('Africa/Nairobi');
        [$from, $to] = config('billing.notice_hours', [8, 20]);

        if ($local->hour < $from || $local->hour >= $to) {
            return;
        }

        if ($account->low_balance_notified_at !== null && $account->low_balance_notified_at->setTimezone('Africa/Nairobi')->isSameDay($local)) {
            return;
        }

        [$shop, $poa] = $this->shop($account->tenant_id);
        $days = $daysCovered === 1 ? '1 more trading day' : "{$daysCovered} more trading days";

        $this->send($account, 'billing_low_balance',
            'Top up to keep Poachy running',
            ["{$shop}'s Poachy balance is KSh ".number_format((float) $account->balance, 2)." — enough for about {$days}.", 'Top up any amount from KSh 10 by M-Pesa.'],
            "Poachy: {$shop}'s balance covers about {$days}. Top up: Paybill {$this->paybill()}, account {$poa}.", $poa);

        $account->forceFill(['low_balance_notified_at' => $now])->save();
    }

    /**
     * @param  array<int, string>  $paragraphs
     */
    private function send(BillingAccount $account, string $template, string $heading, array $paragraphs, ?string $sms, ?string $poa): void
    {
        $mail = new PlainMessageMail($heading, $paragraphs, paybillAccount: $poa);

        if ($sms === null) {
            $email = BusinessDetail::query()->where('tenant_id', $account->tenant_id)->value('business_email');
            if ($email) {
                $this->messenger->email($email, $mail, $account->tenant_id, $template);
            }

            return;
        }

        $this->messenger->toShop($account->tenant_id, $mail, $template, $sms);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function shop(string $tenantId): array
    {
        $name = BusinessDetail::query()->where('tenant_id', $tenantId)->value('business_name') ?? 'Your shop';
        $poa = (string) Tenant::query()->whereKey($tenantId)->value('mpesa_paybill_account');

        return [$name, $poa];
    }

    private function paybill(): string
    {
        return (string) config('messaging.paybill_number');
    }
}
