<?php

namespace Tests\Feature\Signup;

use App\Enums\Central\BillingStatus;
use App\Jobs\Central\ProvisionSignupJob;
use App\Mail\Central\Billing\BillingNoticeMail;
use App\Mail\Central\Signup\TemporaryPasswordMail;
use App\Models\BillingAccount;
use App\Models\BusinessDetail;
use App\Models\Domain;
use App\Models\MessageLog;
use App\Models\SignupRequest;
use App\Models\Tenant;
use App\Models\Tenant\Store;
use App\Models\Tenant\User;
use App\Services\Tenant\Auth\TenantAuthService;
use Database\Seeders\BillingPriceSeeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Feature\Tenant\Concerns\InteractsWithTenantHttpAuth;
use Tests\TestCase;

/**
 * ProvisionSignupJob against real databases: tenant, domains, business details, owner with the
 * chosen password, main branch, trial billing + POA account, welcome email; and clean failure.
 */
class ProvisionSignupJobTest extends TestCase
{
    use InteractsWithTenantHttpAuth;

    private string $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureTenantHttpDatabases();
        $this->seed(BillingPriceSeeder::class);
        Config::set('signup.domains', ['{slug}.signup-test.localhost', '{slug}.signup-test.nip.io']);
        Mail::fake();
        $this->workspace = 'signup-'.Str::lower(Str::random(8));
    }

    protected function tearDown(): void
    {
        $this->deleteTenantHttpFixture("{$this->workspace}.signup-test.localhost");
        SignupRequest::query()->where('workspace', $this->workspace)->delete();
        parent::tearDown();
    }

    private function signup(): SignupRequest
    {
        return SignupRequest::query()->create([
            'shop_name' => 'Signup Test Duka', 'owner_name' => 'Njeri Kamau', 'phone' => '2547'.random_int(10000000, 99999999),
            'email' => "owner-{$this->workspace}@example.com", 'town' => 'Thika', 'branches' => 1, 'workspace' => $this->workspace,
            'phone_verified_at' => now(), 'email_verified_at' => now(), 'terms_accepted_at' => now(),
            'password_hash' => Hash::make('Secret123'), 'status' => SignupRequest::PROVISIONING, 'expires_at' => now()->addDay(),
        ]);
    }

    public function test_it_creates_a_ready_to_use_shop(): void
    {
        $signup = $this->signup();

        ProvisionSignupJob::dispatchSync($signup->id);

        $signup->refresh();
        $this->assertSame(SignupRequest::READY, $signup->status, (string) $signup->failure_reason);
        $this->assertNull($signup->password_hash);

        $tenant = Tenant::query()->findOrFail($signup->tenant_id);
        $this->assertMatchesRegularExpression('/^POA\d+$/', (string) $tenant->mpesa_paybill_account);
        $this->assertEqualsCanonicalizing(
            ["{$this->workspace}.signup-test.localhost", "{$this->workspace}.signup-test.nip.io"],
            Domain::query()->where('tenant_id', $tenant->id)->pluck('domain')->all(),
        );

        $details = BusinessDetail::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('Signup Test Duka', $details->business_name);
        $this->assertSame('active', $details->status);
        $this->assertFalse((bool) $details->is_verified); // listing waits for verification
        $this->assertNotNull($details->onboarded_at);
        $this->assertSame('Thika', $details->city);

        $billing = BillingAccount::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame(BillingStatus::Trial, $billing->status);
        $this->assertTrue($billing->trial_ends_at->between(now()->addDays(29), now()->addDays(31)));

        $tenant->run(function () use ($signup) {
            $owner = User::query()->where('email', $signup->email)->firstOrFail();
            $this->assertTrue($owner->hasRole('owner'));
            $this->assertTrue(Hash::check('Secret123', $owner->password));
            $this->assertNotNull($owner->password_chosen_at);

            // Ready to add products: categories and units for the shop's type were seeded.
            $this->assertGreaterThan(0, DB::table('units_of_measure')->count());
            $this->assertGreaterThan(0, DB::table('product_categories')->count());

            $store = Store::query()->where('is_main_store', true)->sole();
            $this->assertSame('Signup Test Duka', $store->name);

            // First sign-in: the chosen password is kept, no forced change.
            $login = app(TenantAuthService::class)->initiateLogin($signup->email, 'Secret123');
            $this->assertFalse($login['requires_password_change']);
        });

        $this->assertTrue(MessageLog::query()->where('tenant_id', $tenant->id)->where('template', 'signup_welcome')->exists());
    }

    public function test_a_failure_is_recorded_and_leaves_no_half_made_shop(): void
    {
        $other = $this->createTenantHttpFixture("{$this->workspace}.signup-test.nip.io"); // the second domain is already taken
        $signup = $this->signup();

        ProvisionSignupJob::dispatchSync($signup->id);

        $signup->refresh();
        $this->assertSame(SignupRequest::FAILED, $signup->status);
        $this->assertNull($signup->tenant_id);
        $this->assertFalse(Domain::query()->where('domain', "{$this->workspace}.signup-test.localhost")->exists());
        $this->assertSame(1, Tenant::query()->whereKey($other->id)->count());

        $this->deleteTenantHttpFixture("{$this->workspace}.signup-test.nip.io");
    }

    public function test_it_runs_once(): void
    {
        $signup = $this->signup();
        ProvisionSignupJob::dispatchSync($signup->id);
        $tenantId = $signup->fresh()->tenant_id;

        ProvisionSignupJob::dispatchSync($signup->id);

        $this->assertSame($tenantId, $signup->fresh()->tenant_id);
        $this->assertSame(1, Domain::query()->where('domain', "{$this->workspace}.signup-test.localhost")->count());
    }

    public function test_a_shop_opened_by_staff_gets_a_temporary_password_by_email(): void
    {
        $signup = $this->signup();
        $signup->forceFill(['source' => SignupRequest::SOURCE_ADMIN, 'created_by_admin' => '7', 'password_hash' => null])->save();

        ProvisionSignupJob::dispatchSync($signup->id);

        $signup->refresh();
        $this->assertSame(SignupRequest::READY, $signup->status, (string) $signup->failure_reason);

        $password = null;
        Mail::assertQueued(TemporaryPasswordMail::class, function (TemporaryPasswordMail $m) use ($signup, &$password) {
            $password = $m->password;

            return $m->hasTo($signup->email) && $m->workspace === $this->workspace && strlen($m->password) === 14;
        });
        Mail::assertQueued(BillingNoticeMail::class, fn (BillingNoticeMail $m) => $m->kind === 'welcome' && $m->data['byAdmin'] === true);

        Tenant::query()->findOrFail($signup->tenant_id)->run(function () use ($signup, $password) {
            $owner = User::query()->where('email', $signup->email)->firstOrFail();
            $this->assertTrue(Hash::check($password, $owner->password));
            $this->assertNull($owner->password_chosen_at);
            // First sign-in must choose a new password.
            $this->assertTrue(app(TenantAuthService::class)->initiateLogin($signup->email, $password)['requires_password_change']);
        });
    }
}
