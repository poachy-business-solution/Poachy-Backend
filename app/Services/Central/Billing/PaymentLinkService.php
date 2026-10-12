<?php

namespace App\Services\Central\Billing;

use App\Models\BillingTopup;
use App\Models\PaymentLink;
use Illuminate\Support\Str;

/**
 * Payment page links: poachy.com/pay/<token>. The token is shown once (in the email or to the
 * admin who made it) and only its SHA-256 is stored.
 *
 * Rules: valid for 7 days; anyone holding the link can pay the shop's Poachy balance (it only
 * ever moves money *in*); a cancelled or failed prompt leaves it usable; it is used up once a
 * payment through it completes; one prompt at a time per link.
 */
class PaymentLinkService
{
    public const VALID_DAYS = 7;

    /** Seconds before the same link can send another prompt while one is still open. */
    public const PROMPT_COOLDOWN_SECONDS = 60;

    /**
     * @return array{link: PaymentLink, token: string, url: string}
     */
    public function create(string $tenantId, ?float $amount, string $createdBy, string $purpose = 'topup', int $validDays = self::VALID_DAYS): array
    {
        $token = Str::random(43);
        $link = PaymentLink::query()->create([
            'tenant_id' => $tenantId,
            'token_hash' => PaymentLink::hashToken($token),
            'amount' => $amount,
            'purpose' => $purpose,
            'expires_at' => now()->addDays($validDays),
            'created_by' => $createdBy,
        ]);

        return ['link' => $link, 'token' => $token, 'url' => self::url($token)];
    }

    public static function url(string $token): string
    {
        return rtrim((string) config('messaging.site_url'), '/').'/pay/'.$token;
    }

    /** "valid" | "expired" | "used" */
    public function state(PaymentLink $link): string
    {
        return match (true) {
            $link->used_at !== null => 'used',
            $link->isExpired() => 'expired',
            default => 'valid',
        };
    }

    /** An STK top-up through this link that is still waiting for the payer, if any. */
    public function openPrompt(PaymentLink $link): ?BillingTopup
    {
        return BillingTopup::query()
            ->where('payment_link_id', $link->id)
            ->whereIn('status', [BillingTopup::PENDING, BillingTopup::PROCESSING])
            ->where('created_at', '>=', now()->subSeconds(self::PROMPT_COOLDOWN_SECONDS))
            ->latest('id')
            ->first();
    }

    /** Called when a top-up through the link completes. */
    public function markUsed(int $linkId): void
    {
        PaymentLink::query()->whereKey($linkId)->whereNull('used_at')->update(['used_at' => now()]);
    }
}
