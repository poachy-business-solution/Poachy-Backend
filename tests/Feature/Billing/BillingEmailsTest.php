<?php

namespace Tests\Feature\Billing;

use App\Mail\Central\Billing\BillingNoticeMail;
use App\Mail\Central\Billing\BillingReceiptMail;
use App\Models\BillingAccount;
use App\Models\BusinessDetail;
use App\Models\MessageLog;
use App\Models\Tenant;
use App\Services\Central\Billing\BillingNotices;
use Carbon\CarbonImmutable;
use Database\Seeders\BillingPriceSeeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Item 19: every billing email renders with the Paybill number, the shop's account number and
 * (where money is asked for) a pay link; trial reminders go out 7/3/1 days before, once each.
 */
class BillingEmailsTest extends TestCase
{
    private string $tenant;

    private string $poa;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('database.default', 'central');
        Config::set('tenancy.database.central_connection', 'central');
        Config::set('messaging.site_url', 'https://poachy.test');
        Config::set('messaging.paybill_number', '174379');
        DB::connection('central')->beginTransaction();
        $this->seed(BillingPriceSeeder::class);
        Mail::fake();

        $this->tenant = 'mails-'.Str::lower(Str::random(8));
        $this->poa = 'POA7'.random_int(1000, 9999);
        DB::connection('central')->table('tenants')->insert(['id' => $this->tenant, 'mpesa_paybill_account' => $this->poa, 'created_at' => now(), 'updated_at' => now()]);
        BusinessDetail::query()->create([
            'tenant_id' => $this->tenant, 'business_name' => 'Mama Mboga Duka', 'business_type_id' => DB::connection('central')->table('business_types')->value('id'),
            'business_category_id' => DB::connection('central')->table('business_categories')->value('id'),
            'business_phone' => '0712345678', 'business_email' => 'owner@mails-test.example',
        ]);
    }

    protected function tearDown(): void
    {
        DB::connection('central')->rollBack();
        parent::tearDown();
    }

    /** @return array<string, array{0: string, 1: array<string, mixed>, 2: bool}> */
    public static function kinds(): array
    {
        return [
            'welcome' => ['welcome', ['ownerName' => 'Achieng', 'workspace' => 'mama-mboga', 'trialEndsOn' => '12 November 2026', 'dayPrice' => 100.0, 'monthCap' => 2500.0], false],
            'low balance' => ['low_balance', ['balance' => 150.0, 'daysCovered' => 1], true],
            'grace' => ['grace', ['balance' => -100.0], true],
            'limited' => ['limited', ['balance' => -200.0], true],
            'restored' => ['restored', ['balance' => 600.0], false],
            'trial ending' => ['trial_ending', ['daysLeft' => 3, 'trialEndsOn' => 'Thursday 15 October 2026', 'dayPrice' => 100.0, 'monthCap' => 2500.0], true],
            'payment link' => ['payment_link', ['validDays' => 7, 'amount' => 700.0], true],
        ];
    }

    /**
     * @dataProvider kinds
     *
     * @param  array<string, mixed>  $data
     */
    public function test_each_email_shows_paybill_account_and_pay_link(string $kind, array $data, bool $hasButton): void
    {
        $url = 'https://poachy.test/pay/'.str_repeat('a', 43);
        $mail = new BillingNoticeMail($kind, 'Mama Mboga Duka', $this->poa, $kind === 'welcome' ? null : $url, $data);
        $html = $mail->render();

        $this->assertStringContainsString('174379', $html);
        $this->assertStringContainsString($this->poa, $html);
        $this->assertStringContainsString('Mama Mboga Duka', $html);
        if ($kind !== 'welcome') {
            $this->assertStringContainsString($url, $html);
        }
        $this->assertSame($hasButton, str_contains($html, 'Top up now'));
        $this->assertNotEmpty($mail->envelope()->subject);
    }

    public function test_welcome_names_the_workspace_and_trial_end(): void
    {
        $html = (new BillingNoticeMail('welcome', 'Mama Mboga Duka', $this->poa, null, self::kinds()['welcome'][1]))->render();

        $this->assertStringContainsString('mama-mboga', $html);
        $this->assertStringContainsString('12 November 2026', $html);
        $this->assertStringContainsString('KSh 2,500', $html);
    }

    public function test_unknown_kinds_are_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new BillingNoticeMail('free_money', 'x', 'y');
    }

    public function test_trial_reminders_go_out_7_3_and_1_days_before_once_each(): void
    {
        // Trial ends 20 Oct 2026 at 14:00 Nairobi.
        BillingAccount::factory()->create(['tenant_id' => $this->tenant, 'balance' => 0, 'status' => 'trial', 'trial_ends_at' => CarbonImmutable::parse('2026-10-20 14:00', 'Africa/Nairobi')->utc()]);

        foreach (['2026-10-12 09:00' => 0, '2026-10-13 09:00' => 1, '2026-10-14 09:00' => 0, '2026-10-17 09:00' => 1, '2026-10-19 09:00' => 1] as $at => $expected) {
            $this->artisan('billing:trial-reminders', ['--as-of' => $at])->expectsOutputToContain("Trial reminders sent: {$expected}")->assertSuccessful();
        }
        // Running again the same day sends nothing new.
        $this->artisan('billing:trial-reminders', ['--as-of' => '2026-10-19 15:00'])->expectsOutputToContain('Trial reminders sent: 0');

        foreach ([7, 3, 1] as $days) {
            Mail::assertQueued(BillingNoticeMail::class, fn (BillingNoticeMail $m) => $m->kind === 'trial_ending' && $m->data['daysLeft'] === $days
                && $m->hasTo('owner@mails-test.example') && str_starts_with((string) $m->payUrl, 'https://poachy.test/pay/') && $m->data['trialEndsOn'] === 'Tuesday 20 October 2026');
            $this->assertSame(1, MessageLog::query()->where('tenant_id', $this->tenant)->where('template', "billing_trial_ending_{$days}")->count());
        }
        Mail::assertQueuedCount(3);
    }

    public function test_active_shops_get_no_trial_reminder(): void
    {
        BillingAccount::factory()->create(['tenant_id' => $this->tenant, 'balance' => 500, 'status' => 'active', 'trial_ends_at' => CarbonImmutable::parse('2026-10-20 14:00', 'Africa/Nairobi')->utc()]);

        $this->artisan('billing:trial-reminders', ['--as-of' => '2026-10-13 09:00'])->expectsOutputToContain('Trial reminders sent: 0');
        Mail::assertNothingQueued();
    }

    public function test_the_welcome_email_uses_the_template_and_the_trial_end(): void
    {
        BillingAccount::factory()->create(['tenant_id' => $this->tenant, 'balance' => 0, 'status' => 'trial', 'trial_ends_at' => CarbonImmutable::parse('2026-11-12 10:00', 'Africa/Nairobi')->utc()]);

        app(BillingNotices::class)->welcome(Tenant::query()->findOrFail($this->tenant), 'achieng@mails-test.example', 'Achieng', 'mama-mboga');

        Mail::assertQueued(BillingNoticeMail::class, fn (BillingNoticeMail $m) => $m->kind === 'welcome' && $m->hasTo('achieng@mails-test.example')
            && $m->data['trialEndsOn'] === '12 November 2026' && $m->data['workspace'] === 'mama-mboga' && $m->accountNumber === $this->poa);
        $this->assertTrue(MessageLog::query()->where('tenant_id', $this->tenant)->where('template', 'signup_welcome')->exists());
    }

    public function test_the_receipt_shows_a_pay_link(): void
    {
        $html = (new BillingReceiptMail('Mama Mboga Duka', 700, 'FKX1', 650, $this->poa, '13 Oct 2026, 10:00', 'M-Pesa prompt', 'https://poachy.test/pay/xyz'))->render();

        $this->assertStringContainsString('https://poachy.test/pay/xyz', $html);
        $this->assertStringContainsString($this->poa, $html);
        $this->assertStringContainsString('FKX1', $html);
    }
}
