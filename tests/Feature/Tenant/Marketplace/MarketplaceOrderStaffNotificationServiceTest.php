<?php

namespace Tests\Feature\Tenant\Marketplace;

use App\Jobs\Tenant\SendNotificationJob;
use App\Models\Tenant\MarketplaceSale;
use App\Models\Tenant\Store;
use App\Models\Tenant\User;
use App\Services\Tenant\Marketplace\MarketplaceOrderStaffNotificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MarketplaceOrderStaffNotificationServiceTest extends TestCase
{
    private const TEST_DB = 'poachy_test';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'tenant');
        Config::set('database.connections.tenant.database', self::TEST_DB);
        DB::purge('tenant');

        DB::connection('tenant')->statement('SET foreign_key_checks = 0');
        $this->dropTestTables();
        $this->createMinimalSchema();

        Queue::fake();
    }

    protected function tearDown(): void
    {
        $this->dropTestTables();
        DB::connection('tenant')->statement('SET foreign_key_checks = 1');

        parent::tearDown();
    }

    public function test_notify_new_order_reserved_emails_owner_and_store_manager_once(): void
    {
        $owner = $this->createUser('Owner', 'owner@test.com');
        $manager = $this->createUser('Manager', 'manager@test.com');
        $inactiveOwner = $this->createUser('Inactive Owner', 'inactive-owner@test.com', false);
        $this->assignRole($owner->id, 'owner');
        $this->assignRole($inactiveOwner->id, 'owner');
        $store = $this->createStore($manager->id);

        $this->service()->notifyNewOrderReserved($this->payload(), [$store->id]);

        Queue::assertPushed(SendNotificationJob::class, 2);
        $this->assertEmailQueuedFor($owner->email, 'marketplace_order_reserved', 'New marketplace order reserved');
        $this->assertEmailQueuedFor($manager->email, 'marketplace_order_reserved', 'New marketplace order reserved');
        Queue::assertNotPushed(SendNotificationJob::class, fn (SendNotificationJob $job) => $job->recipient === $inactiveOwner->email);
    }

    public function test_notify_payment_confirmed_emails_owner_and_sale_store_manager(): void
    {
        $owner = $this->createUser('Owner', 'owner@test.com');
        $manager = $this->createUser('Manager', 'manager@test.com');
        $this->assignRole($owner->id, 'owner');
        $store = $this->createStore($manager->id);

        $sale = Model::withoutEvents(fn () => MarketplaceSale::create([
            'central_order_id' => 123,
            'sale_number' => 'MKT-ORD-123',
            'store_id' => $store->id,
            'sale_date' => now(),
            'subtotal' => 500,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 500,
            'payment_status' => 'paid',
            'amount_paid' => 500,
            'amount_due' => 0,
            'payment_method' => 'mpesa',
            'fulfillment_type' => 'pickup',
            'fulfillment_status' => 'pending',
        ]));

        $this->service()->notifyPaymentConfirmed($this->payload(), $sale);

        Queue::assertPushed(SendNotificationJob::class, 2);
        $this->assertEmailQueuedFor($owner->email, 'marketplace_payment_confirmed', 'Marketplace payment confirmed');
        $this->assertEmailQueuedFor($manager->email, 'marketplace_payment_confirmed', 'Marketplace payment confirmed');
    }

    private function service(): MarketplaceOrderStaffNotificationService
    {
        return new MarketplaceOrderStaffNotificationService;
    }

    private function payload(): array
    {
        return [
            'order_id' => 123,
            'order_number' => 'MKT-ORD-123',
            'amount' => 500,
            'fulfillment_type' => 'pickup',
            'items' => [
                ['product_name' => 'Test Product', 'quantity' => 2],
            ],
        ];
    }

    private function assertEmailQueuedFor(string $email, string $type, string $subject): void
    {
        Queue::assertPushed(SendNotificationJob::class, function (SendNotificationJob $job) use ($email, $type, $subject) {
            return $job->channel === 'email'
                && $job->recipient === $email
                && $job->message['subject'] === $subject
                && $job->metadata['notification_type'] === $type;
        });
    }

    private function createUser(string $name, string $email, bool $isActive = true): User
    {
        return Model::withoutEvents(fn () => User::create([
            'name' => $name,
            'email' => $email,
            'password' => 'secret',
            'is_active' => $isActive,
        ]));
    }

    private function assignRole(int $userId, string $roleName): void
    {
        $roleId = DB::connection('tenant')->table('roles')->insertGetId([
            'name' => $roleName,
            'guard_name' => 'tenant',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('tenant')->table('model_has_roles')->insert([
            'role_id' => $roleId,
            'model_type' => User::class,
            'model_id' => $userId,
        ]);
    }

    private function createStore(?int $managerId): Store
    {
        return Model::withoutEvents(fn () => Store::create([
            'name' => 'Store '.uniqid(),
            'code' => 'STORE-'.uniqid(),
            'address' => '123 Test Street',
            'manager_id' => $managerId,
        ]));
    }

    private function createMinimalSchema(): void
    {
        $conn = 'tenant';

        Schema::connection($conn)->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->default('');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection($conn)->create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::connection($conn)->create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_primary');
        });

        Schema::connection($conn)->create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('address')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('marketplace_sales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('central_order_id')->unique();
            $table->string('sale_number')->unique();
            $table->unsignedBigInteger('store_id');
            $table->dateTime('sale_date')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('payment_status')->default('paid');
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('amount_due', 12, 2)->default(0);
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('fulfillment_type')->nullable();
            $table->string('fulfillment_status')->default('pending');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function dropTestTables(): void
    {
        foreach (['marketplace_sales', 'stores', 'model_has_roles', 'roles', 'users'] as $table) {
            Schema::connection('tenant')->dropIfExists($table);
        }
    }
}
