<?php

namespace Tests\Feature\Billing;

use App\Enums\Central\BillingLedgerType;
use App\Events\Central\Billing\BillingBalanceCredited;
use App\Exceptions\MpesaException;
use App\Mail\Central\Billing\BillingReceiptMail;
use App\Models\BillingAccount;
use App\Models\BillingLedgerEntry;
use App\Models\BillingTopup;
use App\Models\BusinessDetail;
use App\Models\CentralPaymentLog;
use App\Services\Central\Billing\BillingTopupService;
use App\Services\Shared\Mpesa\Gateways\FakeMpesaGateway;
use App\Support\MpesaCallbacks;
use Database\Seeders\BillingPriceSeeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Top-ups: Paybill (C2B) validation + confirmation to POA accounts, STK prompts and callbacks,
 * receipts and idempotency. Runs in a rolled-back central transaction.
 */
class BillingTopupTest extends TestCase
{
    private string $tenant;

    private string $account;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('database.default', 'central');
        Config::set('tenancy.database.central_connection', 'central');
        DB::connection('central')->beginTransaction();
        $this->seed(BillingPriceSeeder::class);
        Mail::fake();

        $this->tenant = 'topup-'.Str::lower(Str::random(8));
        $this->account = 'POA9'.random_int(1000, 9999);
        DB::connection('central')->table('tenants')->insert(['id' => $this->tenant, 'mpesa_paybill_account' => $this->account, 'created_at' => now(), 'updated_at' => now()]);
        BusinessDetail::query()->create([
            'tenant_id' => $this->tenant, 'business_name' => 'Topup Test Shop', 'business_type_id' => DB::connection('central')->table('business_types')->value('id'),
            'business_category_id' => DB::connection('central')->table('business_categories')->value('id'),
            'business_phone' => '0712345678', 'business_email' => 'owner@topup-test.example',
        ]);
        BillingAccount::factory()->create(['tenant_id' => $this->tenant, 'balance' => -200]);
    }

    protected function tearDown(): void
    {
        DB::connection('central')->rollBack();
        parent::tearDown();
    }

    private function c2b(string $account, float $amount, string $transId = 'FKTOPUP001'): array
    {
        return [
            'TransactionType' => 'Pay Bill', 'TransID' => $transId, 'TransTime' => '20261012101010', 'TransAmount' => (string) $amount,
            'BusinessShortCode' => '174379', 'BillRefNumber' => $account, 'MSISDN' => '254712345678', 'FirstName' => 'Amina',
        ];
    }

    private function balance(): string
    {
        return BillingAccount::query()->where('tenant_id', $this->tenant)->value('balance');
    }

    // ---- Paybill (C2B) ----------------------------------------------------------

    public function test_validation_accepts_any_amount_from_the_minimum_for_a_known_account(): void
    {
        foreach ([10, 137, 2500.5] as $amount) {
            $this->postJson('/api/v1/central/mpesa/c2b/validate/'.MpesaCallbacks::token(), $this->c2b($this->account, $amount))->assertJson(['ResultCode' => '0']);
        }
        $this->postJson('/api/v1/central/mpesa/c2b/validate/'.MpesaCallbacks::token(), $this->c2b(strtolower($this->account), 50))->assertJson(['ResultCode' => '0']);
    }

    public function test_validation_rejects_below_the_minimum_and_unknown_accounts(): void
    {
        $this->postJson('/api/v1/central/mpesa/c2b/validate/'.MpesaCallbacks::token(), $this->c2b($this->account, 9))->assertJson(['ResultCode' => 'C2B00013']);
        $this->postJson('/api/v1/central/mpesa/c2b/validate/'.MpesaCallbacks::token(), $this->c2b('POA00000000', 500))->assertJson(['ResultCode' => 'C2B00011']);
    }

    public function test_confirmation_credits_the_balance_once_and_emails_a_receipt(): void
    {
        Event::fake([BillingBalanceCredited::class]);

        $this->postJson('/api/v1/central/mpesa/c2b/confirm/'.MpesaCallbacks::token(), $this->c2b($this->account, 700))->assertJson(['ResultCode' => 0]);
        $this->postJson('/api/v1/central/mpesa/c2b/confirm/'.MpesaCallbacks::token(), $this->c2b($this->account, 700))->assertJson(['ResultCode' => 0]); // Safaricom retry

        $this->assertSame('500.00', $this->balance());
        $line = BillingLedgerEntry::query()->where('tenant_id', $this->tenant)->sole();
        $this->assertSame(BillingLedgerType::Topup, $line->type);
        $this->assertSame('700.00', $line->amount);
        $this->assertSame('FKTOPUP001', $line->reference);
        $this->assertSame('mpesa_c2b', $line->source);
        Event::assertDispatchedTimes(BillingBalanceCredited::class, 1);
        Mail::assertQueued(BillingReceiptMail::class, fn (BillingReceiptMail $m) => $m->hasTo('owner@topup-test.example') && $m->amount === 700.0 && $m->newBalance === 500.0 && $m->accountNumber === $this->account);
        Mail::assertQueuedCount(1);
    }

    public function test_a_payment_to_an_unknown_account_is_kept_as_unmatched(): void
    {
        $this->postJson('/api/v1/central/mpesa/c2b/confirm/'.MpesaCallbacks::token(), $this->c2b('POA00000000', 300, 'FKUNMATCH1'))->assertJson(['ResultCode' => 0]);

        $this->assertTrue(CentralPaymentLog::query()->where('event', 'c2b_unmatched')->where('transaction_reference', 'FKUNMATCH1')->exists());
        $this->assertSame('-200.00', $this->balance());
    }

    // ---- STK --------------------------------------------------------------------

    public function test_stk_top_up_is_credited_when_the_callback_says_paid(): void
    {
        $topups = app(BillingTopupService::class);
        $topup = $topups->startStk($this->tenant, 699.6, '0712345678', 'test', 'key-1');

        $this->assertSame(BillingTopup::PROCESSING, $topup->status);
        $this->assertSame('700.00', $topup->amount);
        $this->assertSame($this->account, FakeMpesaGateway::find('stk', $topup->checkout_request_id)['account_reference']);

        $this->artisan('poachy:simulate-mpesa', ['type' => 'stk', 'id' => $topup->checkout_request_id])->assertSuccessful();
        $this->artisan('poachy:simulate-mpesa', ['type' => 'stk', 'id' => $topup->checkout_request_id])->assertSuccessful(); // repeated callback

        $topup->refresh();
        $this->assertSame(BillingTopup::COMPLETED, $topup->status);
        $this->assertNotNull($topup->mpesa_receipt);
        $this->assertSame('500.00', $this->balance());
        $this->assertSame(1, BillingLedgerEntry::query()->where('tenant_id', $this->tenant)->count());
        $this->assertSame('mpesa_stk', $topup->ledgerEntry->source);
        Mail::assertQueuedCount(1);
    }

    public function test_a_cancelled_prompt_marks_the_top_up_failed_without_credit(): void
    {
        $topup = app(BillingTopupService::class)->startStk($this->tenant, 100, '0712345678', 'test');

        $this->artisan('poachy:simulate-mpesa', ['type' => 'stk', 'id' => $topup->checkout_request_id, '--fail' => true])->assertSuccessful();

        $this->assertSame(BillingTopup::FAILED, $topup->fresh()->status);
        $this->assertSame('Request cancelled by user', $topup->fresh()->failure_reason);
        $this->assertSame('-200.00', $this->balance());
    }

    public function test_the_same_idempotency_key_never_prompts_twice(): void
    {
        $topups = app(BillingTopupService::class);
        $first = $topups->startStk($this->tenant, 100, '0712345678', 'test', 'same');
        $second = $topups->startStk($this->tenant, 999, '0712345678', 'test', 'same');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, BillingTopup::query()->where('tenant_id', $this->tenant)->count());
    }

    public function test_an_mpesa_error_marks_the_top_up_failed(): void
    {
        try {
            app(BillingTopupService::class)->startStk($this->tenant, 100, '0700999999', 'test');
            $this->fail('Expected an M-Pesa error');
        } catch (MpesaException) {
        }

        $this->assertSame(BillingTopup::FAILED, BillingTopup::query()->where('tenant_id', $this->tenant)->value('status'));
    }

    public function test_a_shop_without_a_billing_account_gets_one_on_its_first_payment(): void
    {
        BillingAccount::query()->where('tenant_id', $this->tenant)->delete();

        $this->postJson('/api/v1/central/mpesa/c2b/confirm/'.MpesaCallbacks::token(), $this->c2b($this->account, 50, 'FKFIRST01'));

        $this->assertSame('50.00', $this->balance());
    }
}
