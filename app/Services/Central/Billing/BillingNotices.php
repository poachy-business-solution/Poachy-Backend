<?php

namespace App\Services\Central\Billing;

use App\Enums\Central\BillingStatus;
use App\Mail\Central\Billing\BillingNoticeMail;
use App\Models\BillingAccount;
use App\Models\BillingPrice;
use App\Models\BusinessDetail;
use App\Models\MessageLog;
use App\Models\Tenant;
use App\Services\Shared\Messaging\Messenger;
use Carbon\CarbonImmutable;

/**
 * Billing emails (and SMS for the urgent ones) to shops. Every email has the Paybill box and,
 * except the welcome before any money is due, a fresh 7-day pay link.
 */
class BillingNotices
{
    /** Trial reminders go out this many Nairobi days before the trial ends. */
    public const TRIAL_REMINDER_DAYS = [7, 3, 1];

    public function __construct(private Messenger $messenger, private PaymentLinkService $links) {}

    public function statusChanged(BillingAccount $account, ?BillingStatus $from, BillingStatus $to): void
    {
        [$shop, $poa] = $this->shop($account->tenant_id);
        $balance = ['balance' => (float) $account->balance];

        match ($to) {
            BillingStatus::Grace => $this->send($account, 'grace', $balance,
                "Poachy: {$shop}'s balance has run out. Top up today to keep full access: Paybill {$this->paybill()}, account {$poa}."),
            BillingStatus::Limited => $this->send($account, 'limited', $balance,
                "Poachy: {$shop} is in limited mode. Selling still works. Top up to restore everything: Paybill {$this->paybill()}, account {$poa}."),
            BillingStatus::Active => in_array($from, [BillingStatus::Grace, BillingStatus::Limited], true)
                ? $this->send($account, 'restored', $balance, null, withLink: false)
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

        $this->send($account, 'low_balance', ['balance' => (float) $account->balance, 'daysCovered' => $daysCovered],
            "Poachy: {$shop}'s balance covers about {$days}. Top up: Paybill {$this->paybill()}, account {$poa}.");

        $account->forceFill(['low_balance_notified_at' => $now])->save();
    }

    /**
     * Trial reminders 7, 3 and 1 Nairobi days before the trial ends; each is sent once per shop.
     * Returns the reminder sent (days left), or null.
     */
    public function trialEnding(BillingAccount $account, CarbonImmutable $now): ?int
    {
        if ($account->status !== BillingStatus::Trial || $account->trial_ends_at === null) {
            return null;
        }

        $today = $now->setTimezone('Africa/Nairobi')->startOfDay();
        $endsOn = CarbonImmutable::parse($account->trial_ends_at)->setTimezone('Africa/Nairobi')->startOfDay();
        $daysLeft = (int) $today->diffInDays($endsOn, false);

        if (! in_array($daysLeft, self::TRIAL_REMINDER_DAYS, true)) {
            return null;
        }

        $template = "billing_trial_ending_{$daysLeft}";
        if (MessageLog::query()->where('tenant_id', $account->tenant_id)->where('template', $template)->exists()) {
            return null;
        }

        $price = BillingPrice::forDate($endsOn);
        $this->send($account, 'trial_ending', [
            'daysLeft' => $daysLeft,
            'trialEndsOn' => $endsOn->format('l j F Y'),
            'dayPrice' => (float) ($price?->first_branch_day ?? 100),
            'monthCap' => (float) ($price?->first_branch_cap ?? 2500),
        ], null, template: $template);

        return $daysLeft;
    }

    /** The welcome email for a newly opened shop. */
    public function welcome(Tenant $tenant, string $email, string $ownerName, string $workspace): void
    {
        $account = BillingAccount::query()->where('tenant_id', $tenant->id)->first();
        $trialEnds = $account?->trial_ends_at === null ? null : CarbonImmutable::parse($account->trial_ends_at)->setTimezone('Africa/Nairobi');
        $price = BillingPrice::forDate(now('Africa/Nairobi'));
        [$shop, $poa] = $this->shop((string) $tenant->id);

        $this->messenger->email($email, new BillingNoticeMail('welcome', $shop, $poa, null, [
            'ownerName' => $ownerName,
            'workspace' => $workspace,
            'trialEndsOn' => $trialEnds?->format('j F Y') ?? '—',
            'dayPrice' => (float) ($price?->first_branch_day ?? 100),
            'monthCap' => (float) ($price?->first_branch_cap ?? 2500),
        ]), (string) $tenant->id, 'signup_welcome');
    }

    /** Emails an existing pay link to the shop's business email. Returns the address, or null. */
    public function paymentLink(string $tenantId, string $url, ?float $amount): ?string
    {
        $email = BusinessDetail::query()->where('tenant_id', $tenantId)->value('business_email');
        if (! $email) {
            return null;
        }

        [$shop, $poa] = $this->shop($tenantId);
        $this->messenger->email($email, new BillingNoticeMail('payment_link', $shop, $poa, $url, [
            'validDays' => PaymentLinkService::VALID_DAYS,
            'amount' => $amount,
        ]), $tenantId, 'billing_payment_link');

        return $email;
    }

    /** A fresh pay link for an email (receipts, reminders). */
    public function payLink(string $tenantId, string $reason): string
    {
        return $this->links->create($tenantId, null, 'system:'.$reason)['url'];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function send(BillingAccount $account, string $kind, array $data, ?string $sms, bool $withLink = true, ?string $template = null): void
    {
        [$shop, $poa] = $this->shop($account->tenant_id);
        $template ??= 'billing_'.$kind;
        $payUrl = $withLink ? $this->payLink($account->tenant_id, $template) : null;
        $mail = new BillingNoticeMail($kind, $shop, $poa, $payUrl, $data);

        if ($sms === null) {
            $email = BusinessDetail::query()->where('tenant_id', $account->tenant_id)->value('business_email');
            if ($email) {
                $this->messenger->email($email, $mail, $account->tenant_id, $template);
            }

            return;
        }

        $this->messenger->toShop($account->tenant_id, $mail, $template, $payUrl === null ? $sms : "{$sms} Or pay here: {$payUrl}");
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
