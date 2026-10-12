<?php

namespace Tests\Feature\Billing;

use App\Mail\Shared\PlainMessageMail;
use App\Models\BillingAccount;
use App\Models\BillingTopup;
use App\Models\BusinessDetail;
use App\Models\PaymentLink;
use App\Services\Central\Billing\BillingStateService;
use App\Services\Central\Billing\PaymentLinkService;
use Carbon\CarbonImmutable;
use Database\Seeders\BillingPriceSeeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pay links: GET /api/v1/public/pay/{token}, POST /stk, GET /topups/{id}; expiry, one-time
 * use, one prompt at a time, rate limits, and links in billing notices.
 */
class PaymentLinkTest extends TestCase
{
    private string $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('database.default', 'central');
        Config::set('tenancy.database.central_connection', 'central');
        Config::set('messaging.site_url', 'https://poachy.test');
        DB::connection('central')->beginTransaction();
        $this->seed(BillingPriceSeeder::class);
        Mail::fake();

        $this->tenant = 'paylink-'.Str::lower(Str::random(8));
        DB::connection('central')->table('tenants')->insert(['id' => $this->tenant, 'mpesa_paybill_account' => 'POA8'.random_int(1000, 9999), 'created_at' => now(), 'updated_at' => now()]);
        BusinessDetail::query()->create([
            'tenant_id' => $this->tenant, 'business_name' => 'Link Shop', 'business_type_id' => DB::connection('central')->table('business_types')->value('id'),
            'business_category_id' => DB::connection('central')->table('business_categories')->value('id'),
            'business_phone' => '0712345678', 'business_email' => 'owner@link-test.example',
        ]);
        BillingAccount::factory()->create(['tenant_id' => $this->tenant, 'balance' => -50, 'status' => 'grace', 'grace_until' => now()->addHours(5)]);
    }

    protected function tearDown(): void
    {
        DB::connection('central')->rollBack();
        parent::tearDown();
    }

    /** @return array{link: PaymentLink, token: string, url: string} */
    private function link(?float $amount = 700): array
    {
        return app(PaymentLinkService::class)->create($this->tenant, $amount, 'test');
    }

    private function prompt(string $token, string $key = 'k1', array $body = ['phone' => '0712345678', 'amount' => 700])
    {
        return $this->postJson("/api/v1/public/pay/{$token}/stk", $body, ['Idempotency-Key' => $key]);
    }

    public function test_a_link_stores_only_a_hash_and_lasts_seven_days(): void
    {
        $made = $this->link();

        $this->assertSame(43, strlen($made['token']));
        $this->assertSame("https://poachy.test/pay/{$made['token']}", $made['url']);
        $this->assertSame(PaymentLink::hashToken($made['token']), $made['link']->token_hash);
        $this->assertFalse(DB::connection('central')->table('payment_links')->where('token_hash', $made['token'])->exists());
        $this->assertEqualsWithDelta(7 * 86400, now()->diffInSeconds($made['link']->expires_at), 5);
    }

    public function test_the_page_shows_how_to_pay_but_never_the_balance(): void
    {
        $made = $this->link();

        $this->getJson("/api/v1/public/pay/{$made['token']}")->assertOk()
            ->assertJsonPath('data.shop_name', 'Link Shop')
            ->assertJsonPath('data.suggested_amount', 700)
            ->assertJsonPath('data.min_amount', 10)
            ->assertJsonPath('data.account_number', DB::connection('central')->table('tenants')->where('id', $this->tenant)->value('mpesa_paybill_account'))
            ->assertJsonMissingPath('data.balance')
            ->assertJsonMissingPath('data.tenant_id');
    }

    public function test_unknown_expired_and_used_links(): void
    {
        $this->getJson('/api/v1/public/pay/'.Str::random(43))->assertNotFound();
        $this->getJson('/api/v1/public/pay/short')->assertNotFound(); // fails the route pattern

        $expired = $this->link();
        $expired['link']->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->getJson("/api/v1/public/pay/{$expired['token']}")->assertStatus(410)->assertJsonPath('errors.state', 'expired');
        $this->prompt($expired['token'])->assertStatus(410);

        $used = $this->link();
        $used['link']->forceFill(['used_at' => now()])->save();
        $this->getJson("/api/v1/public/pay/{$used['token']}")->assertStatus(410)->assertJsonPath('errors.state', 'used');
    }

    public function test_a_prompt_pays_the_shop_and_uses_up_the_link(): void
    {
        $made = $this->link();

        $response = $this->prompt($made['token'])->assertCreated()->assertJsonPath('data.status', 'processing');
        $topup = BillingTopup::query()->findOrFail($response->json('data.id'));
        $this->assertSame($made['link']->id, $topup->payment_link_id);
        $this->assertSame('pay_link:'.$made['link']->id, $topup->initiated_by);

        $this->prompt($made['token'])->assertOk()->assertJsonPath('data.id', $topup->id); // same key = same prompt

        $this->artisan('poachy:simulate-mpesa', ['type' => 'stk', 'id' => $topup->checkout_request_id])->assertSuccessful();

        $this->getJson("/api/v1/public/pay/{$made['token']}/topups/{$topup->id}")->assertOk()->assertJsonPath('data.status', 'completed');
        $this->assertNotNull($made['link']->fresh()->used_at);
        $this->assertSame('650.00', BillingAccount::query()->where('tenant_id', $this->tenant)->value('balance'));
        $this->getJson("/api/v1/public/pay/{$made['token']}")->assertStatus(410);
    }

    public function test_one_prompt_at_a_time_and_a_cancelled_prompt_leaves_the_link_usable(): void
    {
        $made = $this->link();
        $first = BillingTopup::query()->findOrFail($this->prompt($made['token'], 'a')->assertCreated()->json('data.id'));

        $this->prompt($made['token'], 'b')->assertStatus(409)->assertJsonStructure(['errors' => ['retry_after']]);

        $this->artisan('poachy:simulate-mpesa', ['type' => 'stk', 'id' => $first->checkout_request_id, '--fail' => true]);
        $this->assertNull($made['link']->fresh()->used_at);

        $this->prompt($made['token'], 'c')->assertCreated();
    }

    public function test_bad_input_and_rate_limit(): void
    {
        $made = $this->link();

        $this->postJson("/api/v1/public/pay/{$made['token']}/stk", ['phone' => '0712345678', 'amount' => 700])->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
        $this->prompt($made['token'], 'x', ['phone' => '123', 'amount' => 700])->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->prompt($made['token'], 'y', ['phone' => '0712345678', 'amount' => 5])->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->prompt($made['token'], 'z')->assertStatus(429); // 3 prompts a minute per link
    }

    public function test_a_status_poll_only_sees_its_own_links_prompts(): void
    {
        $mine = $this->link();
        $other = $this->link();
        $topup = BillingTopup::query()->findOrFail($this->prompt($other['token'])->json('data.id'));

        $this->getJson("/api/v1/public/pay/{$mine['token']}/topups/{$topup->id}")->assertNotFound();
    }

    public function test_pay_notices_carry_a_fresh_link(): void
    {
        $account = BillingAccount::query()->where('tenant_id', $this->tenant)->firstOrFail();
        $account->forceFill(['status' => 'active', 'balance' => 150, 'grace_until' => null])->save();

        app(BillingStateService::class)->evaluate($account, CarbonImmutable::parse('2026-10-12 10:00', 'Africa/Nairobi'));

        Mail::assertQueued(PlainMessageMail::class, fn (PlainMessageMail $m) => str_starts_with((string) $m->buttonUrl, 'https://poachy.test/pay/') && $m->payUrl === $m->buttonUrl);
        $this->assertSame(1, PaymentLink::query()->where('tenant_id', $this->tenant)->where('created_by', 'system:billing_low_balance')->count());
    }

    public function test_the_console_command_makes_and_emails_a_link(): void
    {
        $poa = DB::connection('central')->table('tenants')->where('id', $this->tenant)->value('mpesa_paybill_account');

        $this->artisan('billing:payment-link', ['tenant' => strtolower($poa), '--amount' => 500, '--email' => true])
            ->expectsOutputToContain('https://poachy.test/pay/')->assertSuccessful();

        $this->assertSame('500.00', PaymentLink::query()->where('tenant_id', $this->tenant)->latest('id')->value('amount'));
        Mail::assertQueued(PlainMessageMail::class, fn (PlainMessageMail $m) => $m->heading === 'Your Poachy payment link' && $m->hasTo('owner@link-test.example'));
    }
}
