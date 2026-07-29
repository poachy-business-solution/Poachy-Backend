<?php

namespace Tests\Feature\Tenant\Inventory;

use App\Enums\Tenant\ExpiryAlertLevel;
use App\Models\Tenant\ExpiryAlert;
use App\Models\Tenant\ProductBatch;
use App\Services\Tenant\Inventory\ExpiryAlertService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class ExpiryAlertServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'tenant');
        Config::set('database.connections.tenant.database', 'poachy_test');
        DB::purge('tenant');
        DB::connection('tenant')->statement('SET foreign_key_checks = 0');

        $this->createMinimalSchema();

        $fakeTenant = new \stdClass;
        $fakeTenant->id = 'test-tenant';
        app()->instance(TenantContract::class, $fakeTenant);
    }

    protected function tearDown(): void
    {
        $this->dropTestTables();
        DB::connection('tenant')->statement('SET foreign_key_checks = 1');
        parent::tearDown();
    }

    public function test_already_expired_batch_with_remaining_quantity_generates_an_expired_alert(): void
    {
        $batch = $this->createBatch([
            'is_expired' => true,
            'expiry_date' => now()->subDays(2)->toDateString(),
            'quantity_remaining_in_base_uom' => 10.0,
        ]);

        $alert = (new ExpiryAlertService)->checkAndGenerateAlert($batch);

        $this->assertNotNull($alert);
        $this->assertSame(ExpiryAlertLevel::EXPIRED, $alert->alert_level);
        $this->assertSame(1, ExpiryAlert::where('batch_id', $batch->id)->count());
    }

    public function test_already_expired_batch_with_zero_remaining_quantity_generates_no_alert(): void
    {
        $batch = $this->createBatch([
            'is_expired' => true,
            'expiry_date' => now()->subDays(2)->toDateString(),
            'quantity_remaining_in_base_uom' => 0.0,
        ]);

        $alert = (new ExpiryAlertService)->checkAndGenerateAlert($batch);

        $this->assertNull($alert);
        $this->assertSame(0, ExpiryAlert::where('batch_id', $batch->id)->count());
    }

    public function test_batch_with_no_expiry_date_generates_no_alert(): void
    {
        $batch = $this->createBatch([
            'is_expired' => false,
            'expiry_date' => null,
            'quantity_remaining_in_base_uom' => 10.0,
        ]);

        $alert = (new ExpiryAlertService)->checkAndGenerateAlert($batch);

        $this->assertNull($alert);
    }

    // =========================================================================
    // Fixture + schema helpers
    // =========================================================================

    private function createBatch(array $overrides = []): ProductBatch
    {
        DB::connection('tenant')->table('tenant_configurations')->insertOrIgnore([
            'config_key' => 'expiry_alerts_enabled',
            'config_value' => json_encode(true),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Model::withoutEvents(fn () => ProductBatch::create(array_merge([
            'store_id' => 1,
            'product_id' => 1,
            'purchase_order_id' => 1,
            'batch_number' => 'BATCH-'.uniqid(),
            'purchase_uom_id' => 1,
            'quantity_received_in_purchase_uom' => 10.0,
            'quantity_received_in_base_uom' => 100.0,
            'quantity_remaining_in_base_uom' => 100.0,
            'cost_per_purchase_uom' => 100.0,
            'cost_per_base_uom' => 10.0,
            'total_cost' => 1000.0,
        ], $overrides)));
    }

    private function dropTestTables(): void
    {
        foreach (['expiry_alerts', 'product_batches', 'products', 'stores', 'tenant_configurations'] as $table) {
            Schema::connection('tenant')->dropIfExists($table);
        }
    }

    private function createMinimalSchema(): void
    {
        $conn = 'tenant';

        Schema::connection($conn)->create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Test Store');
            $table->timestamps();
            $table->softDeletes();
        });

        DB::connection($conn)->table('stores')->insert(['id' => 1, 'created_at' => now(), 'updated_at' => now()]);

        Schema::connection($conn)->create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Test Product');
            $table->timestamps();
            $table->softDeletes();
        });

        DB::connection($conn)->table('products')->insert(['id' => 1, 'created_at' => now(), 'updated_at' => now()]);

        Schema::connection($conn)->create('product_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('purchase_order_id');
            $table->string('batch_number')->unique();
            $table->unsignedBigInteger('purchase_uom_id');
            $table->decimal('quantity_received_in_purchase_uom', 15, 4);
            $table->decimal('quantity_received_in_base_uom', 15, 4);
            $table->decimal('quantity_remaining_in_base_uom', 15, 4);
            $table->decimal('cost_per_purchase_uom', 15, 2);
            $table->decimal('cost_per_base_uom', 15, 2);
            $table->decimal('total_cost', 15, 2);
            $table->date('manufacture_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->boolean('is_expired')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('expiry_alerts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('batch_id');
            $table->string('alert_level');
            $table->date('alert_date');
            $table->integer('days_until_expiry')->nullable();
            $table->boolean('is_resolved')->default(false);
            $table->string('resolution_action')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::connection($conn)->create('tenant_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('config_key')->unique();
            $table->json('config_value');
            $table->string('config_type')->default('general');
            $table->string('config_group')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
}
