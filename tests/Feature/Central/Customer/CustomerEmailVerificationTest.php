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

class CustomerEmailVerificationTest extends TestCase
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
        DB::purge('central');
    }

    protected function tearDown(): void
    {
        Otp::on('central')->whereIn('user_id', $this->createdUserIds)->delete();
        MarketplaceCustomer::on('central')->whereIn('user_id', $this->createdUserIds)->forceDelete();
        User::on('central')->whereIn('id', $this->createdUserIds)->forceDelete();

        parent::tearDown();
    }

    private function makeService(): CustomerAuthService
    {
        return new CustomerAuthService(new CustomerOtpService);
    }

    private function createCustomer(array $userOverrides = []): User
    {
        $user = User::on('central')->create(array_merge([
            'name' => 'Test Customer',
            'email' => 'customer-'.uniqid().'@test.com',
            'password' => bcrypt('Password123'),
            'user_type' => 'customer',
            'email_verified_at' => null,
        ], $userOverrides));

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
    // sendEmailVerificationOtp()
    // =========================================================================

    public function test_send_email_verification_throws_when_already_verified(): void
    {
        $user = $this->createCustomer(['email_verified_at' => now()]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Your email address is already verified.');

        $this->makeService()->sendEmailVerificationOtp($user);
    }

    public function test_send_email_verification_does_not_create_otp_when_already_verified(): void
    {
        $user = $this->createCustomer(['email_verified_at' => now()]);

        try {
            $this->makeService()->sendEmailVerificationOtp($user);
        } catch (ValidationException) {
            // expected
        }

        $this->assertSame(0, Otp::on('central')->where('user_id', $user->id)->count());
    }

    public function test_send_email_verification_creates_otp_and_notifies_when_unverified(): void
    {
        Notification::fake();

        $user = $this->createCustomer();

        $this->makeService()->sendEmailVerificationOtp($user);

        $otp = Otp::on('central')->where('user_id', $user->id)->where('type', CustomerOtpService::TYPE_VERIFY_EMAIL)->first();
        $this->assertNotNull($otp);

        Notification::assertSentTo($user, CustomerOtpNotification::class, function ($notification) {
            return $notification->type === CustomerOtpService::TYPE_VERIFY_EMAIL;
        });
    }

    // =========================================================================
    // verifyEmail()
    // =========================================================================

    public function test_verify_email_with_correct_otp_marks_email_verified(): void
    {
        Notification::fake();

        $user = $this->createCustomer();
        $otp = (new CustomerOtpService)->generateAndSend($user, CustomerOtpService::TYPE_VERIFY_EMAIL);

        $this->makeService()->verifyEmail($user, $otp->otp_code);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_verify_email_throws_with_incorrect_otp_and_leaves_unverified(): void
    {
        Notification::fake();

        $user = $this->createCustomer();
        (new CustomerOtpService)->generateAndSend($user, CustomerOtpService::TYPE_VERIFY_EMAIL);

        $this->expectException(ValidationException::class);

        try {
            $this->makeService()->verifyEmail($user, '0000000');
        } finally {
            $this->assertNull($user->fresh()->email_verified_at);
        }
    }
}
