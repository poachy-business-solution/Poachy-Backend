<?php

namespace Tests\Feature\Tenant\Auth;

use App\Models\Tenant\TenantOtp;
use App\Services\Tenant\Auth\TenantAuthService;
use App\Services\Tenant\Auth\TenantOtpService;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\Feature\Tenant\Concerns\InteractsWithTenantAuthorization;
use Tests\TestCase;

/** The emailed code is the second step of sign-in, never a way around the password. */
class TenantLoginCodeTest extends TestCase
{
    use InteractsWithTenantAuthorization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenantAuthorization();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenantAuthorization();
        Mockery::close();
        parent::tearDown();
    }

    public function test_a_code_is_resent_only_after_the_password_step(): void
    {
        $user = $this->makeTenantUser();
        $otp = Mockery::mock(TenantOtpService::class);
        $otp->shouldReceive('generateAndSendOtp')->twice()->andReturn(new TenantOtp);
        $service = new TenantAuthService($otp);

        // An email address alone (known or not) gets nothing — and the same answer.
        $this->assertNull($service->resendOtp($user->email));
        $this->assertNull($service->resendOtp('nobody-'.uniqid().'@example.com'));

        $service->initiateLogin($user->email, 'password');    // code 1
        $this->assertNotNull($service->resendOtp($user->email)); // code 2
    }

    public function test_a_code_for_an_unknown_email_is_refused_not_an_error(): void
    {
        try {
            app(TenantOtpService::class)->verify('nobody-'.uniqid().'@example.com', '1234567');
            $this->fail('Expected a validation error');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('otp_code', $e->errors());
        }
    }
}
