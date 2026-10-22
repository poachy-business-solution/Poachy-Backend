<?php

namespace Tests\Feature\Central\Admin;

use App\Models\Otp;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** The central admin's emailed code is the second step after the password, never a way around it. */
class AdminLoginCodeTest extends TestCase
{
    private array $users = [];

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('tenancy.database.central_connection', 'central');
        Config::set('database.connections.central.database', env('CENTRAL_DB_DATABASE', 'poachy'));
        DB::purge('central');
        DB::setDefaultConnection('central');
        config(['permission.connection' => 'central']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Mail::fake();
    }

    protected function tearDown(): void
    {
        foreach ($this->users as $user) {
            Otp::query()->where('user_id', $user->id)->delete();
            $user->roles()->detach();
            $user->forceDelete();
        }
        parent::tearDown();
    }

    public function test_a_code_without_the_password_step_never_signs_an_admin_in(): void
    {
        $admin = $this->user('admin');

        // Resend with only an email: nothing is sent, and the answer is the same for anyone.
        $this->postJson('/api/v1/central/auth/admin/resend-otp', ['email' => $admin->email])->assertOk();
        $this->postJson('/api/v1/central/auth/admin/resend-otp', ['email' => 'nobody-'.uniqid().'@example.com'])->assertOk();
        $this->assertSame(0, Otp::query()->where('user_id', $admin->id)->count());

        // A buyer's own login code (same table) is never an admin sign-in.
        $buyer = $this->user('customer');
        Otp::query()->create(['user_id' => $buyer->id, 'otp_code' => '123456', 'type' => 'login', 'expires_at' => now()->addMinutes(10)]);
        $this->postJson('/api/v1/central/auth/admin/verify-otp', ['email' => $buyer->email, 'otp_code' => '123456'])->assertStatus(422);
        $this->assertSame(0, $buyer->tokens()->count());

        // With the password first, the code works and can be resent.
        $this->postJson('/api/v1/central/auth/admin/login', ['email' => $admin->email, 'password' => 'Admin-pass-2026'])->assertOk();
        $this->postJson('/api/v1/central/auth/admin/resend-otp', ['email' => $admin->email])->assertOk();
        $code = Otp::query()->where('user_id', $admin->id)->where('is_used', false)->latest('id')->value('otp_code');
        $this->postJson('/api/v1/central/auth/admin/verify-otp', ['email' => $admin->email, 'otp_code' => $code])->assertOk()->assertJsonStructure(['data' => ['token']]);
    }

    private function user(string $role): User
    {
        $user = User::on('central')->create(['name' => ucfirst($role).' Test', 'email' => $role.'-'.uniqid().'@example.com', 'password' => bcrypt('Admin-pass-2026'), 'user_type' => $role === 'customer' ? 'customer' : 'admin']);
        $user->assignRole($role);
        $this->users[] = $user;

        return $user;
    }
}
