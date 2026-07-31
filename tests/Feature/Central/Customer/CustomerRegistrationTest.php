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
use Tests\TestCase;

class CustomerRegistrationTest extends TestCase
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
        // Spatie Permission ignores config('permission.connection') entirely — its
        // models resolve the connection via database.default, so role assignment
        // needs the default connection pointed at central for this test.
        Config::set('database.default', 'central');
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

    private function baseData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Customer',
            'email' => 'customer-'.uniqid().'@test.com',
            'password' => 'Password123',
            'phone' => '0712'.rand(100000, 999999),
            'date_of_birth' => '2000-01-01',
            'gender' => 'male',
        ], $overrides);
    }

    private function trackAndForget(MarketplaceCustomer $customer): void
    {
        $this->createdUserIds[] = $customer->user_id;
    }

    public function test_register_creates_user_and_marketplace_customer(): void
    {
        $data = $this->baseData();

        $customer = $this->makeService()->register($data);
        $this->trackAndForget($customer);

        $this->assertInstanceOf(MarketplaceCustomer::class, $customer);
        $this->assertTrue($customer->relationLoaded('user'));

        $user = $customer->user;
        $this->assertSame($data['name'], $user->name);
        $this->assertSame($data['email'], $user->email);
        $this->assertSame('customer', $user->user_type);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check($data['password'], $user->password));

        $this->assertNotNull($customer->customer_number);
        $this->assertStringStartsWith('MKT-CUST-', $customer->customer_number);
        $this->assertSame('2000-01-01', $customer->date_of_birth->format('Y-m-d'));
        $this->assertSame('male', $customer->gender);
        // is_active/phone_verified have no value passed to create() — their values
        // only exist as DB-level column defaults, so read them back via a fresh load.
        $fresh = $customer->fresh();
        $this->assertTrue($fresh->is_active);
        $this->assertFalse($fresh->phone_verified);
    }

    public function test_register_assigns_customer_role(): void
    {
        $customer = $this->makeService()->register($this->baseData());
        $this->trackAndForget($customer);

        $this->assertTrue($customer->user->fresh()->hasRole('customer'));
    }

    public function test_register_normalizes_local_format_phone_to_international(): void
    {
        $customer = $this->makeService()->register($this->baseData([
            'phone' => '0712345'.rand(100, 999),
        ]));
        $this->trackAndForget($customer);

        $this->assertStringStartsWith('+254712345', $customer->phone);
    }

    public function test_register_defaults_marketing_and_sms_opt_in_to_true_when_omitted(): void
    {
        $customer = $this->makeService()->register($this->baseData());
        $this->trackAndForget($customer);

        $this->assertTrue($customer->accepts_marketing);
        $this->assertTrue($customer->accepts_sms);
    }

    public function test_register_respects_explicit_opt_out_of_marketing_and_sms(): void
    {
        $customer = $this->makeService()->register($this->baseData([
            'accepts_marketing' => false,
            'accepts_sms' => false,
        ]));
        $this->trackAndForget($customer);

        $this->assertFalse($customer->accepts_marketing);
        $this->assertFalse($customer->accepts_sms);
    }

    public function test_register_allows_omitting_optional_profile_fields(): void
    {
        $data = $this->baseData();
        unset($data['date_of_birth'], $data['gender']);

        $customer = $this->makeService()->register($data);
        $this->trackAndForget($customer);

        $this->assertNull($customer->date_of_birth);
        $this->assertNull($customer->gender);
    }

    public function test_register_sends_an_email_verification_otp(): void
    {
        Notification::fake();

        $customer = $this->makeService()->register($this->baseData());
        $this->trackAndForget($customer);

        $otp = Otp::on('central')->where('user_id', $customer->user_id)->where('type', CustomerOtpService::TYPE_VERIFY_EMAIL)->first();
        $this->assertNotNull($otp);

        Notification::assertSentTo($customer->user, CustomerOtpNotification::class, function ($notification) {
            return $notification->type === CustomerOtpService::TYPE_VERIFY_EMAIL;
        });
    }
}
