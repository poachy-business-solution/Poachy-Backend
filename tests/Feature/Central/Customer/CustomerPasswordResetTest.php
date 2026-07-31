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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerPasswordResetTest extends TestCase
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
    // initiatePasswordReset()
    // =========================================================================

    public function test_initiate_password_reset_returns_true_for_unknown_email_without_sending_otp(): void
    {
        Notification::fake();

        $result = $this->makeService()->initiatePasswordReset('nobody-'.uniqid().'@test.com');

        $this->assertTrue($result);
        Notification::assertNothingSent();
    }

    public function test_initiate_password_reset_returns_true_for_inactive_account_without_sending_otp(): void
    {
        Notification::fake();

        $user = $this->createCustomer(customerOverrides: ['is_active' => false]);

        $result = $this->makeService()->initiatePasswordReset($user->email);

        $this->assertTrue($result);
        $this->assertSame(0, Otp::on('central')->where('user_id', $user->id)->count());
        Notification::assertNothingSent();
    }

    public function test_initiate_password_reset_sends_otp_for_active_account(): void
    {
        Notification::fake();

        $user = $this->createCustomer();

        $result = $this->makeService()->initiatePasswordReset($user->email);

        $this->assertTrue($result);

        $otp = Otp::on('central')->where('user_id', $user->id)->where('type', CustomerOtpService::TYPE_PASSWORD_RESET)->first();
        $this->assertNotNull($otp);

        Notification::assertSentTo($user, CustomerOtpNotification::class, function ($notification) {
            return $notification->type === CustomerOtpService::TYPE_PASSWORD_RESET;
        });
    }

    // =========================================================================
    // resetPassword()
    // =========================================================================

    public function test_reset_password_with_correct_otp_updates_password(): void
    {
        Notification::fake();

        $user = $this->createCustomer();
        $otp = (new CustomerOtpService)->generateAndSend($user, CustomerOtpService::TYPE_PASSWORD_RESET);

        $this->makeService()->resetPassword($user->email, $otp->otp_code, 'NewPassword456');

        $this->assertTrue(Hash::check('NewPassword456', $user->fresh()->password));
    }

    public function test_reset_password_revokes_all_existing_tokens(): void
    {
        Notification::fake();

        $user = $this->createCustomer();
        $user->createToken('device-a');
        $user->createToken('device-b');

        $otp = (new CustomerOtpService)->generateAndSend($user, CustomerOtpService::TYPE_PASSWORD_RESET);
        $this->makeService()->resetPassword($user->email, $otp->otp_code, 'NewPassword456');

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_reset_password_throws_with_incorrect_otp_and_leaves_password_unchanged(): void
    {
        Notification::fake();

        $user = $this->createCustomer();
        $originalHash = $user->password;
        (new CustomerOtpService)->generateAndSend($user, CustomerOtpService::TYPE_PASSWORD_RESET);

        $this->expectException(ValidationException::class);

        try {
            $this->makeService()->resetPassword($user->email, '0000000', 'NewPassword456');
        } finally {
            $this->assertSame($originalHash, $user->fresh()->password);
        }
    }
}
