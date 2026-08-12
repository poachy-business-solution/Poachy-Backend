<?php

namespace Tests\Feature\Tenant\Notifications;

use App\Jobs\Tenant\SendNotificationJob;
use App\Models\Tenant\TenantDeviceToken;
use App\Models\Tenant\User;
use App\Services\Tenant\Notifications\PushNotificationService;
use App\Services\Tenant\Notifications\TenantDeviceTokenService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class TenantDeviceTokenServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'tenant');
        Config::set('database.connections.tenant.database', 'poachy_test');
        DB::purge('tenant');

        $this->dropTables();
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        $this->dropTables();

        parent::tearDown();
    }

    public function test_register_upserts_device_token_for_current_user(): void
    {
        $user = $this->createUser();
        $service = new TenantDeviceTokenService;

        $first = $service->register($user, [
            'token' => 'push-token-001',
            'platform' => 'ios',
            'device_id' => 'device-a',
            'device_name' => 'iPhone 15',
            'app_version' => '1.0.0',
        ]);

        $second = $service->register($user, [
            'token' => 'push-token-001',
            'platform' => 'ios',
            'device_id' => 'device-a',
            'device_name' => 'iPhone 15 Pro',
            'app_version' => '1.1.0',
        ]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, TenantDeviceToken::count());
        $this->assertSame('iPhone 15 Pro', $second->device_name);
        $this->assertSame('1.1.0', $second->app_version);
        $this->assertNull($second->revoked_at);
        $this->assertNotNull($second->last_seen_at);
    }

    public function test_revoke_deactivates_only_the_current_users_token(): void
    {
        $service = new TenantDeviceTokenService;
        $owner = $this->createUser(email: 'owner@example.com');
        $cashier = $this->createUser(email: 'cashier@example.com');

        $service->register($owner, ['token' => 'owner-token', 'platform' => 'android']);
        $service->register($cashier, ['token' => 'cashier-token', 'platform' => 'android']);

        $this->assertSame(1, $service->revoke($owner, 'owner-token'));

        $this->assertFalse(TenantDeviceToken::where('token_hash', $service->hashToken('owner-token'))->first()->isActive());
        $this->assertTrue(TenantDeviceToken::where('token_hash', $service->hashToken('cashier-token'))->first()->isActive());
    }

    public function test_push_service_resolves_active_tokens_from_user_metadata(): void
    {
        Log::spy();

        $user = $this->createUser();
        $service = new TenantDeviceTokenService;
        $service->register($user, ['token' => 'active-token', 'platform' => 'ios']);
        $service->register($user, ['token' => 'revoked-token', 'platform' => 'ios']);
        $service->revoke($user, 'revoked-token');

        $sent = (new PushNotificationService($service))->send(
            recipient: $user->email,
            message: ['subject' => 'Low stock', 'body' => 'Restock Product One'],
            metadata: ['user_id' => $user->id, 'stock_alert_id' => 5]
        );

        $this->assertSame(1, $sent);
        Log::shouldHaveReceived('info')
            ->with('Push notification queued for provider', Mockery::on(
                fn (array $context) => $context['user_id'] === $user->id
                    && $context['title'] === 'Low stock'
                    && $context['body'] === 'Restock Product One'
                    && $context['data']['stock_alert_id'] === '5'
            ))
            ->once();
    }

    public function test_send_notification_job_uses_push_service_for_push_channel(): void
    {
        $pushService = Mockery::mock(PushNotificationService::class);
        $pushService
            ->shouldReceive('send')
            ->once()
            ->with(
                'owner@example.com',
                ['subject' => 'New order', 'body' => 'Order #1001 is ready to fulfil'],
                ['user_id' => 1]
            )
            ->andReturn(2);

        (new SendNotificationJob(
            channel: 'push',
            recipient: 'owner@example.com',
            message: ['subject' => 'New order', 'body' => 'Order #1001 is ready to fulfil'],
            metadata: ['user_id' => 1]
        ))->handle($pushService);
    }

    private function createUser(string $email = 'owner@example.com'): User
    {
        return User::create([
            'name' => 'Owner',
            'email' => $email,
            'password' => 'password',
            'is_active' => true,
        ]);
    }

    private function dropTables(): void
    {
        Schema::connection('tenant')->dropIfExists('tenant_device_tokens');
        Schema::connection('tenant')->dropIfExists('users');
    }

    private function createSchema(): void
    {
        Schema::connection('tenant')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('tenant_device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->text('token');
            $table->enum('platform', ['ios', 'android', 'web']);
            $table->string('device_id')->nullable();
            $table->string('device_name')->nullable();
            $table->string('app_version', 50)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }
}
