<?php

namespace Tests\Feature\Payment;

use App\Support\MpesaCallbacks;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/** Item 47 runbook: C2B URLs are derived from APP_URL + the callback secret, never printed in clear. */
class MpesaRegisterC2BCommandTest extends TestCase
{
    public function test_it_needs_https_and_never_prints_the_secret(): void
    {
        Config::set('mpesa.driver', 'fake');
        Config::set('mpesa.c2b_validation_url', '');
        Config::set('mpesa.c2b_confirmation_url', '');

        Config::set('app.url', 'http://localhost');
        $this->artisan('mpesa:register-c2b')->assertFailed();

        Config::set('app.url', 'https://api.poachy.test');
        $this->artisan('mpesa:register-c2b')
            ->expectsOutputToContain('https://api.poachy.test/api/v1/central/mpesa/c2b/confirm/••••')
            ->doesntExpectOutputToContain(MpesaCallbacks::token())
            ->assertSuccessful();
    }
}
