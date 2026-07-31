<?php

namespace Tests\Feature\Central\Customer;

use App\Models\MarketplaceCustomer;
use App\Models\Otp;
use App\Models\User;
use App\Notifications\Central\Auth\CustomerOtpNotification;
use App\Services\Central\Customer\CustomerAuthService;
use App\Services\Central\Customer\CustomerOtpService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerLoginTest extends TestCase
{
    private array $createdUserIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.connections.central.host', env('CENTRAL_DB_HOST', '127.0.0.1'));
        Config::set('database.connections.central.port', env('CENTRAL_DB_PORT', '3306'));
        Config::set('database.connections.central.database', env('CENTRAL_DB_DATABASE', 'poachy'));
        Config::set('database.connections.central.username', env('CENTRAL_DB_USERNAME', 'root'));
        Config::set('database.connections.central.password', env('CENTRAL_DB_PASSWORD', ''));
        // Sanctum's PersonalAccessToken model has no explicit connection — it
        // resolves via database.default, so point that at central for token tests.
        Config::set('database.default', 'central');
        DB::purge('central');
    }

    protected function tearDown(): void
    {
        DB::connection('central')->table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->whereIn('tokenable_id', $this->createdUserIds)
            ->delete();
        Otp::on('central')->whereIn('user_id', $this->createdUserIds)->delete();
        MarketplaceCustomer::on('central')->whereIn('user_id', $this->createdUserIds)->forceDelete();
        User::on('central')->whereIn('id', $this->createdUserIds)->forceDelete();

        parent::tearDown();
    }

    private function makeService(): CustomerAuthService
    {
        return new CustomerAuthService(new CustomerOtpService);
    }

    private function createCustomer(array $userOverrides = [], array $customerOverrides = []): User
    {
        $user = User::on('central')->create(array_merge([
            'name' => 'Test Customer',
            'email' => 'customer-'.uniqid().'@test.com',
            'password' => bcrypt('Password123'),
            'user_type' => 'customer',
        ], $userOverrides));

        $this->createdUserIds[] = $user->id;

        MarketplaceCustomer::on('central')->create(array_merge([
            'user_id' => $user->id,
            'customer_number' => 'MKT-'.uniqid(),
            'phone' => '0712'.rand(100000, 999999),
            'is_active' => true,
        ], $customerOverrides));

        return $user->fresh();
    }

    // =========================================================================
    // initiateLogin()
    // =========================================================================

    public function test_initiate_login_throws_generic_message_for_unknown_email(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The provided credentials are incorrect.');

        $this->makeService()->initiateLogin('nobody-'.uniqid().'@test.com', 'whatever');
    }

    public function test_initiate_login_throws_generic_message_for_wrong_password(): void
    {
        $user = $this->createCustomer();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The provided credentials are incorrect.');

        $this->makeService()->initiateLogin($user->email, 'WrongPassword123');
    }

    public function test_initiate_login_throws_for_inactive_account(): void
    {
        $user = $this->createCustomer(customerOverrides: ['is_active' => false]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('This account is inactive. Please contact support.');

        $this->makeService()->initiateLogin($user->email, 'Password123');
    }

    public function test_initiate_login_does_not_create_an_otp_on_failure(): void
    {
        $user = $this->createCustomer();

        try {
            $this->makeService()->initiateLogin($user->email, 'WrongPassword123');
        } catch (ValidationException) {
            // expected
        }

        $this->assertSame(0, Otp::on('central')->where('user_id', $user->id)->count());
    }

    public function test_initiate_login_sends_login_otp_and_returns_email_on_success(): void
    {
        Notification::fake();

        $user = $this->createCustomer();

        $email = $this->makeService()->initiateLogin($user->email, 'Password123');

        $this->assertSame($user->email, $email);

        $otp = Otp::on('central')->where('user_id', $user->id)->where('type', CustomerOtpService::TYPE_LOGIN)->first();
        $this->assertNotNull($otp);

        Notification::assertSentTo($user, CustomerOtpNotification::class, function ($notification) {
            return $notification->type === CustomerOtpService::TYPE_LOGIN;
        });
    }

    // =========================================================================
    // completeLogin()
    // =========================================================================

    public function test_complete_login_with_correct_otp_issues_token_and_updates_login_tracking(): void
    {
        Notification::fake();

        $user = $this->createCustomer();
        $otp = (new CustomerOtpService)->generateAndSend($user, CustomerOtpService::TYPE_LOGIN);

        $result = $this->makeService()->completeLogin($user->email, $otp->otp_code, 'iphone-15');

        $this->assertArrayHasKey('customer', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertNotEmpty($result['token']);

        $customer = $user->marketplaceCustomer->fresh();
        $this->assertNotNull($customer->last_login_at);
        $this->assertNotNull($customer->last_login_ip);

        $this->assertSame(1, DB::connection('central')->table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $user->id)
            ->where('name', 'iphone-15')
            ->count());
    }

    public function test_complete_login_replaces_existing_token_with_same_device_name(): void
    {
        Notification::fake();

        $user = $this->createCustomer();
        $user->createToken('iphone-15');

        $this->assertSame(1, $user->tokens()->where('name', 'iphone-15')->count());

        $otp = (new CustomerOtpService)->generateAndSend($user, CustomerOtpService::TYPE_LOGIN);
        $this->makeService()->completeLogin($user->email, $otp->otp_code, 'iphone-15');

        // Still exactly one token for this device name — the old one was replaced, not stacked.
        $this->assertSame(1, $user->tokens()->where('name', 'iphone-15')->count());
    }

    public function test_complete_login_throws_with_incorrect_otp_and_issues_no_token(): void
    {
        Notification::fake();

        $user = $this->createCustomer();
        (new CustomerOtpService)->generateAndSend($user, CustomerOtpService::TYPE_LOGIN);

        $this->expectException(ValidationException::class);

        try {
            $this->makeService()->completeLogin($user->email, '0000000', 'web');
        } finally {
            $this->assertSame(0, $user->tokens()->count());
        }
    }

    public function test_complete_login_throws_after_three_failed_attempts(): void
    {
        Notification::fake();

        $user = $this->createCustomer();
        (new CustomerOtpService)->generateAndSend($user, CustomerOtpService::TYPE_LOGIN);

        $service = $this->makeService();

        foreach (range(1, 3) as $_) {
            try {
                $service->completeLogin($user->email, '0000000', 'web');
            } catch (ValidationException) {
                // expected for each of the first 3 attempts
            }
        }

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Too many failed attempts');

        $service->completeLogin($user->email, '0000000', 'web');
    }

    // =========================================================================
    // logout()
    // =========================================================================

    public function test_logout_revokes_only_the_current_token_not_other_devices(): void
    {
        $user = $this->createCustomer();

        $tokenA = $user->createToken('device-a');
        $user->createToken('device-b');

        // Simulate the request having authenticated with device-a's token.
        $user->withAccessToken($tokenA->accessToken);

        $this->makeService()->logout($user);

        $this->assertSame(0, $user->tokens()->where('name', 'device-a')->count());
        $this->assertSame(1, $user->tokens()->where('name', 'device-b')->count());
    }
}
