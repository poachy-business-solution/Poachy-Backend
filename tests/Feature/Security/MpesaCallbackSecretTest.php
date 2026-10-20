<?php

namespace Tests\Feature\Security;

use App\Support\MpesaCallbacks;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Item 44: Daraja does not sign callbacks, so a forged "payment received" must not reach the
 * handlers — every STK/C2B callback URL ends in a secret, optionally limited to Safaricom's IPs.
 */
class MpesaCallbackSecretTest extends TestCase
{
    public function test_callback_urls_carry_the_secret_and_bare_or_wrong_paths_are_refused(): void
    {
        Config::set('app.url', 'https://api.poachy.test');
        Config::set('mpesa.billing_stk_callback_url', '');
        $token = MpesaCallbacks::token();

        $this->assertMatchesRegularExpression('/^[a-f0-9]{40}$/', $token);
        $this->assertSame("https://api.poachy.test/api/v1/central/mpesa/stk/billing/callback/{$token}", MpesaCallbacks::url('stk_billing'));
        Config::set('mpesa.c2b_confirmation_url', 'https://pay.poachy.co.ke/c2b/confirm/');
        $this->assertSame("https://pay.poachy.co.ke/c2b/confirm/{$token}", MpesaCallbacks::url('c2b_confirmation'));
        $this->assertStringNotContainsString($token, MpesaCallbacks::masked('c2b_confirmation'));

        $forged = ['TransID' => 'FAKE123', 'TransAmount' => '5000', 'BillRefNumber' => 'POA00001', 'MSISDN' => '254700000000'];
        foreach (['c2b/confirm', 'c2b/validate', 'stk/callback', 'stk/billing/callback', 'stk/subscription/callback'] as $path) {
            $this->postJson("/api/v1/central/mpesa/{$path}", $forged)->assertNotFound();
            $this->postJson("/api/v1/central/mpesa/{$path}/".str_repeat('a', 40), $forged)->assertForbidden();
        }
    }

    public function test_when_an_ip_list_is_set_only_those_callers_get_through(): void
    {
        Config::set('mpesa.callback_ips', ['196.201.214.0/24']);
        $url = '/api/v1/central/mpesa/stk/billing/callback/'.MpesaCallbacks::token();

        $this->postJson($url, ['Body' => []], [])->assertForbidden(); // 127.0.0.1
        $this->withServerVariables(['REMOTE_ADDR' => '196.201.214.200'])->postJson($url, ['Body' => []])->assertStatus(200);
    }
}
