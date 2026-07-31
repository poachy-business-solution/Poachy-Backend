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

class CustomerPasswordUpdateTest extends TestCase
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

    private function createCustomer(): User
    {
        $user = User::on('central')->create([
            'name' => 'Test Customer',
            'email' => 'customer-'.uniqid().'@test.com',
            'password' => bcrypt('Password123'),
            'user_type' => 'customer',
        ]);

        $this->createdUserIds[] = $user->id;

        MarketplaceCustomer::on('central')->create([
            'user_id' => $user->id,
            'customer_number' => 'MKT-'.uniqid(),
            'phone' => '0712'.rand(100000, 999999),
            'is_active' => true,
        ]);

        return $user->fresh();
    }

    // =========================================================================
    // initiatePasswordUpdate()
    // =========================================================================

    public function test_initiate_password_update_throws_for_incorrect_current_password(): void
    {
        $user = $this->createCustomer();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The current password is incorrect.');

        $this->makeService()->initiatePasswordUpdate($user, 'WrongPassword');
    }

    public function test_initiate_password_update_does_not_send_otp_on_failure(): void
    {
        $user = $this->createCustomer();

        try {
            $this->makeService()->initiatePasswordUpdate($user, 'WrongPassword');
        } catch (ValidationException) {
            // expected
        }

        $this->assertSame(0, Otp::on('central')->where('user_id', $user->id)->count());
    }

    public function test_initiate_password_update_sends_otp_with_correct_current_password(): void
    {
        Notification::fake();

        $user = $this->createCustomer();

        $result = $this->makeService()->initiatePasswordUpdate($user, 'Password123');

        $this->assertTrue($result);

        $otp = Otp::on('central')->where('user_id', $user->id)->where('type', CustomerOtpService::TYPE_UPDATE_PASSWORD)->first();
        $this->assertNotNull($otp);

        Notification::assertSentTo($user, CustomerOtpNotification::class, function ($notification) {
            return $notification->type === CustomerOtpService::TYPE_UPDATE_PASSWORD;
        });
    }

    // =========================================================================
    // confirmPasswordUpdate()
    // =========================================================================

    public function test_confirm_password_update_with_correct_otp_updates_password(): void
    {
        Notification::fake();

        $user = $this->createCustomer();
        $otp = (new CustomerOtpService)->generateAndSend($user, CustomerOtpService::TYPE_UPDATE_PASSWORD);

        $this->makeService()->confirmPasswordUpdate($user, $otp->otp_code, 'NewPassword456');

        $this->assertTrue(Hash::check('NewPassword456', $user->fresh()->password));
    }

    public function test_confirm_password_update_throws_with_incorrect_otp_and_leaves_password_unchanged(): void
    {
        Notification::fake();

        $user = $this->createCustomer();
        $originalHash = $user->password;
        (new CustomerOtpService)->generateAndSend($user, CustomerOtpService::TYPE_UPDATE_PASSWORD);

        $this->expectException(ValidationException::class);

        try {
            $this->makeService()->confirmPasswordUpdate($user, '0000000', 'NewPassword456');
        } finally {
            $this->assertSame($originalHash, $user->fresh()->password);
        }
    }

    public function test_confirm_password_update_revokes_other_tokens_but_keeps_the_current_one(): void
    {
        Notification::fake();

        $user = $this->createCustomer();
        $current = $user->createToken('device-a');
        $user->createToken('device-b');

        // Simulate the request having authenticated with device-a's token.
        $user->withAccessToken($current->accessToken);

        $otp = (new CustomerOtpService)->generateAndSend($user, CustomerOtpService::TYPE_UPDATE_PASSWORD);
        $this->makeService()->confirmPasswordUpdate($user, $otp->otp_code, 'NewPassword456');

        $this->assertSame(1, $user->tokens()->count());
        $this->assertSame(1, $user->tokens()->where('name', 'device-a')->count());
        $this->assertSame(0, $user->tokens()->where('name', 'device-b')->count());
    }

    public function test_confirm_password_update_revokes_all_tokens_when_no_current_token_is_set(): void
    {
        Notification::fake();

        $user = $this->createCustomer();
        $user->createToken('device-a');
        $user->createToken('device-b');

        $otp = (new CustomerOtpService)->generateAndSend($user, CustomerOtpService::TYPE_UPDATE_PASSWORD);
        $this->makeService()->confirmPasswordUpdate($user, $otp->otp_code, 'NewPassword456');

        $this->assertSame(0, $user->tokens()->count());
    }
}
