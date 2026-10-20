<?php

namespace Tests\Feature\Tenant\Concerns;

use App\Models\Tenant\Sale;
use App\Models\Tenant\SaleItem;
use App\Models\Tenant\SalePayment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;

/**
 * A minimal tenant schema for the owner report tests, plus a sale builder.
 */
trait CreatesReportTables
{
    private array $reportTables = [
        'marketplace_sales', 'inventory_movements', 'inventory', 'store_products', 'product_variants', 'product_batches', 'cash_movements',
        'trade_lines', 'trade_partners', 'suppliers', 'customers', 'expenses', 'expense_categories',
        'sale_refund_items', 'sale_refunds', 'sale_payments', 'sale_items', 'sales', 'shift_assignments',
        'products', 'categories', 'stores', 'users', 'tenant_configurations',
    ];

    protected function setUpReportTables(): void
    {
        Config::set('database.default', 'tenant');
        Config::set('database.connections.tenant.database', 'poachy_test');
        DB::purge('tenant');
        DB::connection('tenant')->statement('SET foreign_key_checks = 0');

        $fakeTenant = new \stdClass;
        $fakeTenant->id = 'test-tenant';
        app()->instance(TenantContract::class, $fakeTenant);

        $this->dropReportTables();
        $s = Schema::connection('tenant');

        $s->create('tenant_configurations', function (Blueprint $t) {
            $t->id();
            $t->string('config_key')->unique();
            $t->json('config_value')->nullable();
            $t->string('config_type')->nullable();
            $t->string('config_group')->nullable();
            $t->text('description')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        $s->create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->nullable();
            $t->timestamps();
        });
        $s->create('stores', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->boolean('is_active')->default(true);
            $t->boolean('is_main_store')->default(false);
            $t->timestamps();
            $t->softDeletes();
        });
        $s->create('categories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->timestamps();
        });
        $s->create('products', function (Blueprint $t) {
            $t->id();
            $t->string('uuid')->nullable();
            $t->string('name');
            $t->unsignedBigInteger('category_id')->nullable();
            $t->decimal('base_selling_price', 15, 2)->default(0);
            $t->integer('reorder_level')->default(0);
            $t->boolean('is_active')->default(true);
            $t->boolean('requires_serial_tracking')->default(false);
            $t->boolean('requires_batch_tracking')->default(false);
            $t->unsignedBigInteger('base_uom_id')->default(1);
            $t->timestamps();
            $t->softDeletes();
        });
        $s->create('shift_assignments', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('shift_id')->default(1);
            $t->unsignedBigInteger('store_id');
            $t->unsignedBigInteger('user_id');
            $t->date('shift_date');
            $t->timestamp('actual_start')->nullable();
            $t->timestamp('actual_end')->nullable();
            $t->string('status')->default('completed');
            $t->boolean('started_automatically')->default(false);
            $t->boolean('closed_automatically')->default(false);
            $t->decimal('opening_cash', 15, 2)->nullable();
            $t->string('opening_cash_source', 20)->nullable();
            $t->decimal('closing_cash', 15, 2)->nullable();
            $t->decimal('cash_banked', 15, 2)->nullable();
            $t->string('cash_variance_reason')->nullable();
            $t->timestamps();
        });
        $s->create('sales', function (Blueprint $t) {
            $t->id();
            $t->string('sale_number');
            $t->unsignedBigInteger('store_id');
            $t->unsignedBigInteger('shift_assignment_id')->nullable();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->unsignedBigInteger('served_by')->nullable();
            $t->dateTime('sale_date');
            $t->decimal('subtotal', 15, 2);
            $t->decimal('tax_amount', 15, 2)->default(0);
            $t->decimal('total_amount', 15, 2);
            $t->string('payment_status');
            $t->decimal('amount_paid', 15, 2)->default(0);
            $t->decimal('amount_due', 15, 2)->default(0);
            $t->string('payment_method');
            $t->timestamps();
            $t->softDeletes();
        });
        $s->create('sale_payments', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('sale_id');
            $t->decimal('amount', 15, 2);
            $t->string('payment_method');
            $t->timestamps();
        });
        $s->create('sale_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('sale_id');
            $t->unsignedBigInteger('product_id')->nullable();
            $t->unsignedBigInteger('product_variant_id')->nullable();
            $t->unsignedBigInteger('bundle_id')->nullable();
            $t->string('description')->nullable();
            $t->unsignedBigInteger('uom_id')->nullable();
            $t->decimal('quantity', 12, 4);
            $t->decimal('quantity_in_base_uom', 12, 4);
            $t->decimal('unit_price', 15, 2);
            $t->decimal('list_unit_price', 15, 2)->nullable();
            $t->boolean('is_giveaway')->default(false);
            $t->string('price_override_reason')->nullable();
            $t->decimal('unit_cost', 15, 2)->nullable();
            $t->decimal('discount_amount', 15, 2)->default(0);
            $t->unsignedBigInteger('tax_rate_id')->nullable();
            $t->decimal('tax_amount', 15, 2)->default(0);
            $t->decimal('subtotal', 15, 2);
            $t->timestamps();
            $t->softDeletes();
        });
        $s->create('sale_refunds', function (Blueprint $t) {
            $t->id();
            $t->string('refund_number')->nullable();
            $t->unsignedBigInteger('original_sale_id');
            $t->unsignedBigInteger('store_id')->nullable();
            $t->date('refund_date');
            $t->decimal('refund_amount', 15, 2);
            $t->string('refund_method');
            $t->string('reason');
            $t->string('status');
            $t->unsignedBigInteger('processed_by')->nullable();
            $t->timestamps();
        });
        $s->create('sale_refund_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('refund_id');
            $t->unsignedBigInteger('sale_item_id');
            $t->unsignedBigInteger('product_id')->nullable();
            $t->decimal('quantity_refunded', 15, 4);
            $t->decimal('quantity_refunded_in_base_uom', 15, 4);
            $t->decimal('refund_amount', 15, 2);
            $t->timestamps();
        });
        $s->create('expense_categories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('code')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        $s->create('expenses', function (Blueprint $t) {
            $t->id();
            $t->string('expense_number')->nullable();
            $t->unsignedBigInteger('store_id')->nullable();
            $t->unsignedBigInteger('shift_assignment_id')->nullable();
            $t->unsignedBigInteger('category_id');
            $t->decimal('amount', 15, 2);
            $t->text('description')->nullable();
            $t->date('expense_date');
            $t->string('payment_method')->default('cash');
            $t->string('paid_from', 20)->nullable();
            $t->boolean('is_recurring')->default(false);
            $t->string('recurrence_frequency')->nullable();
            $t->integer('recurrence_interval')->default(1);
            $t->date('recurrence_start_date')->nullable();
            $t->date('recurrence_end_date')->nullable();
            $t->unsignedBigInteger('parent_expense_id')->nullable();
            $t->string('approval_status')->default('approved');
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
        $s->create('customers', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('phone')->nullable();
            $t->decimal('current_debt', 15, 2)->default(0);
            $t->timestamps();
            $t->softDeletes();
        });
        $s->create('suppliers', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->decimal('outstanding_balance', 12, 2)->default(0);
            $t->timestamps();
            $t->softDeletes();
        });
        $s->create('trade_partners', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->softDeletes();
        });
        $s->create('trade_lines', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('trade_partner_id');
            $t->unsignedBigInteger('store_id')->default(1);
            $t->string('direction', 10);
            $t->unsignedBigInteger('product_id')->default(1);
            $t->decimal('quantity', 15, 4);
            $t->decimal('unit_price', 15, 2);
            $t->decimal('unit_cost', 15, 2)->nullable();
            $t->decimal('quantity_returned', 15, 4)->default(0);
            $t->decimal('amount_paid', 15, 2)->default(0);
            $t->string('status', 10)->default('open');
            $t->timestamp('opened_at');
            $t->timestamp('settled_at')->nullable();
            $t->unsignedBigInteger('recorded_by')->default(1);
            $t->timestamps();
        });
        $s->create('cash_movements', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('shift_assignment_id')->nullable();
            $t->string('direction', 3);
            $t->decimal('amount', 15, 2);
            $t->timestamps();
        });
        $s->create('product_batches', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('store_id');
            $t->unsignedBigInteger('product_id');
            $t->unsignedBigInteger('product_variant_id')->nullable();
            $t->decimal('quantity_remaining_in_base_uom', 15, 4)->default(0);
            $t->decimal('cost_per_base_uom', 15, 4)->default(0);
            $t->boolean('is_expired')->default(false);
            $t->timestamps();
            $t->softDeletes();
        });
        $s->create('product_variants', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->nullable();
            $t->unsignedBigInteger('product_id');
            $t->string('variant_name');
            $t->string('sku')->nullable();
            $t->json('attributes')->nullable();
            $t->unsignedBigInteger('uom_id')->nullable();
            $t->decimal('uom_quantity', 12, 4)->default(1);
            $t->decimal('quantity_in_base_uom', 12, 4)->default(1);
            $t->decimal('variant_price', 15, 2)->nullable();
            $t->decimal('cost_price', 15, 4)->nullable();
            $t->decimal('reorder_level', 12, 4)->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->softDeletes();
        });
        $s->create('store_products', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('store_id');
            $t->unsignedBigInteger('product_id');
            $t->unsignedBigInteger('product_variant_id')->nullable();
            $t->decimal('store_selling_price', 15, 2)->nullable();
            $t->integer('min_stock_level')->default(0);
            $t->boolean('is_available')->default(true);
            $t->timestamps();
            $t->softDeletes();
        });
        $s->create('inventory', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('store_id');
            $t->unsignedBigInteger('product_id');
            $t->unsignedBigInteger('product_variant_id')->nullable();
            $t->decimal('quantity_on_hand', 15, 4)->default(0);
            $t->decimal('average_cost', 15, 4)->nullable();
            $t->decimal('quantity_reserved', 15, 4)->default(0);
            $t->decimal('quantity_available', 15, 4)->default(0);
            $t->timestamps();
            $t->softDeletes();
        });
        $s->create('inventory_movements', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('store_id');
            $t->unsignedBigInteger('product_id');
            $t->unsignedBigInteger('product_variant_id')->nullable();
            $t->string('movement_type');
            $t->decimal('quantity_in_base_uom', 15, 4);
            $t->decimal('unit_cost_in_base_uom', 15, 4)->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        $s->create('marketplace_sales', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('central_order_id')->nullable();
            $t->string('sale_number')->unique();
            $t->unsignedBigInteger('store_id');
            $t->dateTime('sale_date');
            $t->decimal('subtotal', 12, 2)->default(0);
            $t->decimal('tax_amount', 12, 2)->default(0);
            $t->decimal('discount_amount', 12, 2)->default(0);
            $t->decimal('total_amount', 12, 2)->default(0);
            $t->string('payment_status')->default('paid');
            $t->decimal('amount_paid', 12, 2)->default(0);
            $t->decimal('amount_due', 12, 2)->default(0);
            $t->string('payment_method')->nullable();
            $t->string('payment_reference')->nullable();
            $t->string('fulfillment_type')->default('delivery');
            $t->string('fulfillment_status')->default('pending');
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        DB::connection('tenant')->table('stores')->insert([
            ['id' => 1, 'name' => 'CBD', 'is_active' => true, 'is_main_store' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Westlands', 'is_active' => true, 'is_main_store' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    protected function dropReportTables(): void
    {
        foreach ($this->reportTables as $table) {
            Schema::connection('tenant')->dropIfExists($table);
        }
    }

    protected function tearDownReportTables(): void
    {
        $this->dropReportTables();
        DB::connection('tenant')->statement('SET foreign_key_checks = 1');
    }

    /**
     * A sale at a UTC moment. Items: product_id, quantity, unit_price, unit_cost,
     * and optionally list_unit_price / is_giveaway.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $extra
     */
    protected function reportSale(string $atUtc, array $items, string $method = 'cash', int $storeId = 1, float $tax = 0, array $extra = []): Sale
    {
        return Model::withoutEvents(function () use ($atUtc, $items, $method, $storeId, $tax, $extra) {
            $total = collect($items)->sum(fn ($i) => ($i['is_giveaway'] ?? false) ? 0 : $i['quantity'] * $i['unit_price']) + $tax;
            $sale = Sale::create(array_merge([
                'sale_number' => 'S-'.uniqid(),
                'store_id' => $storeId,
                'sale_date' => Carbon::parse($atUtc, 'UTC'),
                'subtotal' => $total - $tax,
                'tax_amount' => $tax,
                'total_amount' => $total,
                'payment_status' => 'paid',
                'amount_paid' => $method === 'credit' ? 0 : $total,
                'amount_due' => $method === 'credit' ? $total : 0,
                'payment_method' => $method,
            ], $extra));
            SalePayment::create(['sale_id' => $sale->id, 'payment_method' => $method, 'amount' => $total]);
            foreach ($items as $item) {
                SaleItem::create(array_merge([
                    'sale_id' => $sale->id,
                    'quantity_in_base_uom' => $item['quantity'],
                    'discount_amount' => 0,
                    'tax_amount' => 0,
                    'subtotal' => ($item['is_giveaway'] ?? false) ? 0 : $item['quantity'] * $item['unit_price'],
                ], $item));
            }

            return $sale;
        });
    }
}
