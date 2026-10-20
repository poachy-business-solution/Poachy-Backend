<?php

namespace Tests\Feature\Central\Customer;

use App\Models\MarketplaceCustomer;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Buyer sign-up over HTTP: the web page's Turnstile token is required and checked (fake driver
 * here: any token but "fail" passes) before an account is created.
 */
class CustomerRegisterHttpTest extends TestCase
{
    private string $email;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('tenancy.database.central_connection', 'central');
        Config::set('database.connections.central.host', env('CENTRAL_DB_HOST', '127.0.0.1'));
        Config::set('database.connections.central.port', env('CENTRAL_DB_PORT', '3306'));
        Config::set('database.connections.central.database', env('CENTRAL_DB_DATABASE', 'poachy'));
        Config::set('database.connections.central.username', env('CENTRAL_DB_USERNAME', 'root'));
        Config::set('database.connections.central.password', env('CENTRAL_DB_PASSWORD', ''));
        Config::set('database.default', 'central'); // roles (Spatie) resolve via the default connection
        Config::set('turnstile.driver', 'fake');
        DB::purge('central');
        Mail::fake();
        $this->email = 'register-'.uniqid().'@example.com';
    }

    protected function tearDown(): void
    {
        $user = User::on('central')->where('email', $this->email)->first();
        if ($user) {
            MarketplaceCustomer::on('central')->where('user_id', $user->id)->forceDelete();
            DB::connection('central')->table('model_has_roles')->where('model_id', $user->id)->where('model_type', User::class)->delete();
            $user->forceDelete();
        }
        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Web Buyer',
            'email' => $this->email,
            'phone' => '07'.random_int(10000000, 99999999),
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'turnstile_token' => 'XXXX.DUMMY.TOKEN.XXXX',
        ], $overrides);
    }

    public function test_register_requires_a_passing_turnstile_token(): void
    {
        $this->postJson('/api/v1/central/marketplace/auth/register', $this->payload(['turnstile_token' => null]))
            ->assertUnprocessable()->assertJsonValidationErrors('turnstile_token');
        $this->postJson('/api/v1/central/marketplace/auth/register', $this->payload(['turnstile_token' => 'fail']))
            ->assertUnprocessable()->assertJsonValidationErrors('turnstile_token');
        $this->assertFalse(User::on('central')->where('email', $this->email)->exists());

        $this->postJson('/api/v1/central/marketplace/auth/register', $this->payload())->assertCreated();
        $this->assertTrue(User::on('central')->where('email', $this->email)->exists());
    }
}
