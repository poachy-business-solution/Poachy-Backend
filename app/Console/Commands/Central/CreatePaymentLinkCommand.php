<?php

namespace App\Console\Commands\Central;

use App\Mail\Shared\PlainMessageMail;
use App\Models\BusinessDetail;
use App\Models\Tenant;
use App\Services\Central\Billing\PaymentLinkService;
use App\Services\Shared\Messaging\Messenger;
use Illuminate\Console\Command;

/**
 * Internal: create a pay link for a shop (and optionally email it). The admin panel uses the
 * same PaymentLinkService through the private admin API.
 */
class CreatePaymentLinkCommand extends Command
{
    protected $signature = 'billing:payment-link {tenant : Tenant id or POA account number} {--amount= : Suggested amount} {--email : Email the link to the shop}';

    protected $description = 'Create a payment page link for a shop';

    public function handle(PaymentLinkService $links, Messenger $messenger): int
    {
        $tenant = Tenant::query()->whereKey($this->argument('tenant'))->orWhere('mpesa_paybill_account', strtoupper((string) $this->argument('tenant')))->first();

        if ($tenant === null) {
            $this->error('No such shop.');

            return self::FAILURE;
        }

        $amount = $this->option('amount') !== null ? (float) $this->option('amount') : null;
        $made = $links->create((string) $tenant->id, $amount, 'console');
        $this->line($made['url']);
        $this->info('Valid until '.$made['link']->expires_at->setTimezone('Africa/Nairobi')->format('j M Y H:i').' (Nairobi).');

        if ($this->option('email')) {
            $email = BusinessDetail::query()->where('tenant_id', $tenant->id)->value('business_email');
            if (! $email) {
                $this->warn('The shop has no business email.');

                return self::SUCCESS;
            }
            $messenger->email($email, new PlainMessageMail(
                'Your Poachy payment link',
                ['Use this link to top up your Poachy balance by M-Pesa. It works for '.PaymentLinkService::VALID_DAYS.' days.'],
                'Top up now',
                $made['url'],
                (string) $tenant->mpesa_paybill_account,
                $made['url'],
            ), (string) $tenant->id, 'billing_payment_link');
            $this->info("Emailed to {$email}.");
        }

        return self::SUCCESS;
    }
}
