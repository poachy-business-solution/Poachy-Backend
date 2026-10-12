<?php

namespace Tests\Feature\Signup;

use App\Jobs\Central\ProvisionSignupJob;
use App\Mail\Central\Signup\SignupCodeMail;
use App\Models\BusinessDetail;
use App\Models\MessageLog;
use App\Models\SignupRequest;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Public sign-up API: start (Turnstile, one shop per phone/email), codes, workspace names,
 * complete (queues provisioning), status, rate limits. Central DB in a rolled-back transaction.
 */
class SignupFlowTest extends TestCase
{
    private string $phone;

    private string $email;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('database.default', 'central');
        Config::set('tenancy.database.central_connection', 'central');
        Config::set('turnstile.driver', 'fake');
        Config::set('signup.domains', ['{slug}.localhost', '{slug}.10.0.2.2.nip.io']);
        DB::connection('central')->beginTransaction();
        Mail::fake();
        Bus::fake([ProvisionSignupJob::class]);

        $this->phone = '0712'.random_int(100000, 999999);
        $this->email = 'njeri+'.Str::lower(Str::random(6)).'@example.com';
    }

    protected function tearDown(): void
    {
        DB::connection('central')->rollBack();
        parent::tearDown();
    }

    private function details(array $overrides = []): array
    {
        return $overrides + [
            'shop_name' => "Mama Njeri's Shop", 'owner_name' => 'Njeri Kamau', 'phone' => $this->phone,
            'email' => $this->email, 'town' => 'Thika', 'branches' => 1, 'turnstile_token' => 'ok',
        ];
    }

    private function start(array $overrides = [])
    {
        return $this->postJson('/api/v1/public/signup/start', $this->details($overrides));
    }

    /** @return array{0: string, 1: string} phone code, email code */
    private function codes(string $signupId): array
    {
        $sms = MessageLog::query()->where('template', 'signup_phone_code')->where('channel', 'sms')->latest('id')->value('subject');
        preg_match('/(\d{6})/', (string) $sms, $phone);
        $email = null;
        Mail::assertQueued(SignupCodeMail::class, function (SignupCodeMail $m) use (&$email) {
            $email = $m->code;

            return true;
        });

        return [$phone[1], (string) $email];
    }

    private function verified(): string
    {
        $id = $this->start()->assertCreated()->json('data.signup_id');
        [$phone, $email] = $this->codes($id);
        $this->postJson('/api/v1/public/signup/verify', ['signup_id' => $id, 'phone_code' => $phone, 'email_code' => $email])->assertOk();

        return $id;
    }

    private function complete(string $id, array $overrides = [])
    {
        return $this->postJson('/api/v1/public/signup/complete', $overrides + [
            'signup_id' => $id, 'workspace' => 'mama-njeri-'.Str::lower(Str::random(5)), 'password' => 'Secret123', 'password_confirmation' => 'Secret123', 'accept_terms' => true,
        ]);
    }

    public function test_start_sends_both_codes_and_suggests_a_workspace(): void
    {
        $res = $this->start()->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.suggested_workspace', 'mama-njeris-shop')
            ->assertJsonPath('data.verified', false);

        $this->assertStringContainsString('*', $res->json('data.phone'));
        $signup = SignupRequest::query()->findOrFail($res->json('data.signup_id'));
        $this->assertSame('254'.substr($this->phone, 1), $signup->phone);
        $this->assertNotNull($signup->phone_code_hash);
        Mail::assertQueued(SignupCodeMail::class, fn (SignupCodeMail $m) => $m->hasTo($this->email));
        $this->assertSame(1, MessageLog::query()->where('template', 'signup_phone_code')->where('recipient', '+'.$signup->phone)->count());
    }

    public function test_start_refuses_bots_foreign_numbers_and_bad_details(): void
    {
        $this->start(['turnstile_token' => 'fail'])->assertUnprocessable()->assertJsonValidationErrors('turnstile_token');
        $this->start(['phone' => '+1 415 555 0100'])->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->start(['email' => 'not-an-email', 'shop_name' => ''])->assertUnprocessable()->assertJsonValidationErrors(['email', 'shop_name']);
        $this->start(['branches' => 0])->assertUnprocessable()->assertJsonValidationErrors('branches');
    }

    public function test_one_shop_per_phone_and_per_email(): void
    {
        $tenant = 'existing-'.Str::lower(Str::random(6));
        DB::connection('central')->table('tenants')->insert(['id' => $tenant, 'created_at' => now(), 'updated_at' => now()]);
        BusinessDetail::query()->create([
            'tenant_id' => $tenant, 'business_name' => 'Existing', 'business_type_id' => DB::connection('central')->table('business_types')->value('id'),
            'business_category_id' => DB::connection('central')->table('business_categories')->value('id'), 'business_phone' => '+254'.substr($this->phone, 1), 'business_email' => 'other@example.com',
        ]);
        $this->start()->assertUnprocessable()->assertJsonValidationErrors('phone');

        $this->start(['phone' => '0712'.random_int(100000, 999999), 'email' => 'other@example.com'])->assertUnprocessable()->assertJsonValidationErrors('phone');
    }

    public function test_wrong_codes_count_attempts_and_lock_after_five(): void
    {
        $id = $this->start()->json('data.signup_id');
        [$phone, $email] = $this->codes($id);

        $this->postJson('/api/v1/public/signup/verify', ['signup_id' => $id, 'phone_code' => $phone, 'email_code' => $email === '000000' ? '111111' : '000000'])
            ->assertUnprocessable()->assertJsonValidationErrors('email_code')->assertJsonMissingValidationErrors('phone_code');

        SignupRequest::query()->whereKey($id)->update(['code_attempts' => 5]);
        $this->postJson('/api/v1/public/signup/verify', ['signup_id' => $id, 'phone_code' => $phone, 'email_code' => $email])
            ->assertUnprocessable()->assertJsonValidationErrors('codes');
    }

    public function test_expired_codes_need_a_resend_and_resends_are_limited(): void
    {
        $id = $this->start()->json('data.signup_id');
        SignupRequest::query()->whereKey($id)->update(['codes_expire_at' => now()->subMinute()]);
        [$phone, $email] = $this->codes($id);

        $this->postJson('/api/v1/public/signup/verify', ['signup_id' => $id, 'phone_code' => $phone, 'email_code' => $email])->assertUnprocessable();

        $this->postJson('/api/v1/public/signup/resend', ['signup_id' => $id])->assertOk();
        $this->postJson('/api/v1/public/signup/resend', ['signup_id' => $id])->assertOk();
        $this->postJson('/api/v1/public/signup/resend', ['signup_id' => $id])->assertUnprocessable(); // 3 sends in all
        $this->postJson('/api/v1/public/signup/resend', ['signup_id' => Str::uuid()->toString()])->assertNotFound();
    }

    public function test_correct_codes_verify(): void
    {
        $id = $this->verified();

        $this->getJson("/api/v1/public/signup/status/{$id}")->assertOk()->assertJsonPath('data.status', 'verified')->assertJsonPath('data.verified', true);
        $this->assertNull(SignupRequest::query()->find($id)->phone_code_hash);
    }

    public function test_workspace_names(): void
    {
        DB::connection('central')->table('tenants')->insert(['id' => 'taken-'.Str::random(6), 'created_at' => now(), 'updated_at' => now()]);
        DB::connection('central')->table('domains')->insert(['domain' => 'duka-taken.localhost', 'tenant_id' => DB::connection('central')->table('tenants')->latest('created_at')->value('id'), 'created_at' => now(), 'updated_at' => now()]);
        $check = fn (string $n) => $this->getJson('/api/v1/public/signup/workspace-available?name='.urlencode($n))->assertOk()->json('data');

        $this->assertTrue($check('mama-njeri')['available']);
        $this->assertFalse($check('ab')['available']);
        $this->assertFalse($check('Bad Name!')['available']);
        $this->assertFalse($check('double--dash')['available']);
        $this->assertSame('That name is reserved.', $check('admin')['reason']);
        $taken = $check('duka-taken');
        $this->assertSame('That name is taken.', $taken['reason']);
        $this->assertSame('duka-taken-2', $taken['suggestion']);
    }

    public function test_complete_queues_provisioning_once(): void
    {
        $id = $this->verified();

        $this->complete($id, ['workspace' => 'mama-njeri-test'])->assertStatus(202)->assertJsonPath('data.status', 'provisioning')->assertJsonPath('data.workspace', 'mama-njeri-test');
        $this->complete($id, ['workspace' => 'something-else'])->assertStatus(202)->assertJsonPath('data.workspace', 'mama-njeri-test');

        Bus::assertDispatchedTimes(ProvisionSignupJob::class, 1);
        $signup = SignupRequest::query()->find($id);
        $this->assertNotNull($signup->terms_accepted_at);
        $this->assertTrue(password_verify('Secret123', $signup->password_hash));
    }

    public function test_complete_rules(): void
    {
        $unverified = $this->start()->json('data.signup_id');
        $this->complete($unverified)->assertUnprocessable()->assertJsonValidationErrors('codes');

        $this->phone = '0712'.random_int(100000, 999999);
        $this->email = 'b'.$this->email;
        $id = $this->verified();
        $this->complete($id, ['password' => 'short', 'password_confirmation' => 'short'])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->complete($id, ['accept_terms' => false])->assertUnprocessable()->assertJsonValidationErrors('accept_terms');
        $this->complete($id, ['workspace' => 'admin'])->assertUnprocessable()->assertJsonValidationErrors('workspace');
        $this->complete(Str::uuid()->toString())->assertNotFound();

        // A name being set up for someone else is taken.
        SignupRequest::query()->whereKeyNot($id)->first()->forceFill(['workspace' => 'busy-name', 'status' => 'provisioning'])->save();
        $this->complete($id, ['workspace' => 'busy-name'])->assertUnprocessable()->assertJsonValidationErrors('workspace');
    }

    public function test_a_finished_sign_up_blocks_another_with_the_same_phone(): void
    {
        $id = $this->verified();
        $this->complete($id)->assertStatus(202);

        $this->start()->assertUnprocessable()->assertJsonValidationErrors('phone');
    }

    public function test_status_never_shows_more_than_needed(): void
    {
        $id = $this->start()->json('data.signup_id');

        $data = $this->getJson("/api/v1/public/signup/status/{$id}")->assertOk()->json('data');
        $this->assertArrayNotHasKey('password_hash', $data);
        $this->assertStringNotContainsString(substr($this->phone, 1), (string) $data['phone']);
        $this->getJson('/api/v1/public/signup/status/not-a-uuid')->assertNotFound();
    }

    public function test_starts_are_rate_limited_per_phone(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->start(['email' => "x{$i}".$this->email]);
        }

        $this->start(['email' => 'x9'.$this->email])->assertStatus(429);
    }

    public function test_business_types_for_the_form(): void
    {
        $this->getJson('/api/v1/public/signup/business-types')->assertOk()->assertJsonStructure(['data' => [['id', 'name', 'type']]]);
    }
}
