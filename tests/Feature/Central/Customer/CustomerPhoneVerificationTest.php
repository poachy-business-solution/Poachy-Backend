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

class CustomerPhoneVerificationTest extends TestCase
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

    private function createUnverifiedCustomer(): User
    {
        $user = User::on('central')->create([
            'name' => 'Test Customer',
            'email' => 'customer-'.uniqid().'@test.com',
            'password' => bcrypt('password'),
            'user_type' => 'customer',
        ]);

        $this->createdUserIds[] = $user->id;

        MarketplaceCustomer::on('central')->create([
            'user_id' => $user->id,
            'customer_number' => 'MKT-'.uniqid(),
            'phone' => '0712'.rand(100000, 999999),
            'phone_verified' => false,
        ]);

        return $user->fresh();
    }

    private function makeService(): CustomerAuthService
    {
        return new CustomerAuthService(new CustomerOtpService);
    }

    public function test_send_phone_verification_creates_an_otp_and_notifies_by_email(): void
    {
        Notification::fake();

        $user = $this->createUnverifiedCustomer();

        $this->makeService()->sendPhoneVerificationOtp($user);

        $otp = Otp::on('central')->where('user_id', $user->id)->where('type', CustomerOtpService::TYPE_VERIFY_PHONE)->first();

        $this->assertNotNull($otp);
        $this->assertFalse($otp->is_used);
        Notification::assertSentTo($user, CustomerOtpNotification::class, function ($notification) {
            return $notification->type === CustomerOtpService::TYPE_VERIFY_PHONE;
        });
    }

    public function test_send_phone_verification_throws_when_already_verified(): void
    {
        $user = $this->createUnverifiedCustomer();
        $user->marketplaceCustomer->update(['phone_verified' => true]);

        $this->expectException(ValidationException::class);

        $this->makeService()->sendPhoneVerificationOtp($user);
    }

    public function test_confirm_phone_verification_marks_phone_verified_with_correct_code(): void
    {
        Notification::fake();

        $user = $this->createUnverifiedCustomer();
        $otp = (new CustomerOtpService)->generateAndSend($user, CustomerOtpService::TYPE_VERIFY_PHONE);

        $this->makeService()->verifyPhone($user, $otp->otp_code);

        $this->assertTrue($user->marketplaceCustomer->fresh()->phone_verified);
        $this->assertNotNull($user->marketplaceCustomer->fresh()->phone_verified_at);
    }

    public function test_confirm_phone_verification_throws_with_incorrect_code(): void
    {
        Notification::fake();

        $user = $this->createUnverifiedCustomer();
        (new CustomerOtpService)->generateAndSend($user, CustomerOtpService::TYPE_VERIFY_PHONE);

        $this->expectException(ValidationException::class);

        try {
            $this->makeService()->verifyPhone($user, '0000000');
        } finally {
            $this->assertFalse($user->marketplaceCustomer->fresh()->phone_verified);
        }
    }

    public function test_confirm_phone_verification_throws_after_three_failed_attempts(): void
    {
        Notification::fake();

        $user = $this->createUnverifiedCustomer();
        (new CustomerOtpService)->generateAndSend($user, CustomerOtpService::TYPE_VERIFY_PHONE);

        $service = $this->makeService();

        foreach (range(1, 3) as $_) {
            try {
                $service->verifyPhone($user, '0000000');
            } catch (ValidationException) {
                // expected for each of the first 3 attempts
            }
        }

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Too many failed attempts');

        $service->verifyPhone($user, '0000000');
    }
}
