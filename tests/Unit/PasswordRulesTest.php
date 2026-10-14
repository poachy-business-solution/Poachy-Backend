<?php

namespace Tests\Unit;

use App\Http\Requests\Public\Signup\CompleteSignupRequest;
use App\Http\Requests\Tenant\Auth\ChangePasswordRequest;
use App\Http\Requests\Tenant\Auth\UpdatePasswordRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/** The app's first-login and settings password rules match web sign-up (letters + numbers, 8+). */
class PasswordRulesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 200)]);
    }

    public function test_a_password_without_a_symbol_or_capital_is_accepted_everywhere(): void
    {
        foreach ([new CompleteSignupRequest, new ChangePasswordRequest, new UpdatePasswordRequest] as $request) {
            $rules = ['password' => $request->rules()['password']];
            $this->assertTrue(Validator::make(['password' => 'duka2026pass', 'password_confirmation' => 'duka2026pass'], $rules)->passes(), $request::class);
            $this->assertFalse(Validator::make(['password' => 'dukaonly', 'password_confirmation' => 'dukaonly'], $rules)->passes(), $request::class.' letters only');
            $this->assertFalse(Validator::make(['password' => 'duka20', 'password_confirmation' => 'duka20'], $rules)->passes(), $request::class.' too short');
        }
    }
}
