<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSION = 'manage-trade-partners';

    /**
     * Shop-to-shop trading: neighbour shops lend each other goods and settle
     * by bringing them back or paying. Recorded on this shop's side only, so
     * it works whether or not the neighbour uses Poachy.
     *
     * - trade_partners: the neighbour shops.
     * - trade_lines: one per product lent ("lent": they took my goods, they
     *   owe me) or borrowed ("borrowed": I took theirs, I owe), open until
     *   returned or paid.
     * - trade_events: the history — took, brought back, paid.
     */
    public function up(): void
    {
        Schema::create('trade_partners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });

        Schema::create('trade_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trade_partner_id')->constrained('trade_partners')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->string('direction', 10);
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('unit_cost', 15, 2)->nullable();
            $table->decimal('quantity_returned', 15, 4)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->string('status', 10)->default('open');
            $table->timestamp('opened_at');
            $table->timestamp('settled_at')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(['trade_partner_id', 'status', 'opened_at']);
            $table->index(['trade_partner_id', 'product_id']);
        });

        Schema::create('trade_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trade_partner_id')->constrained('trade_partners')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('trade_line_id')->nullable()->constrained('trade_lines')->nullOnDelete();
            $table->string('type', 20);
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->decimal('quantity', 15, 4)->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('payment_method', 10)->nullable();
            $table->string('payment_reference', 100)->nullable();
            $table->foreignId('cash_movement_id')->nullable()->constrained('cash_movements')->nullOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('occurred_at');
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(['trade_partner_id', 'occurred_at']);
        });

        $this->grantPermission();
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_events');
        Schema::dropIfExists('trade_lines');
        Schema::dropIfExists('trade_partners');

        if (Schema::hasTable('permissions')) {
            $permissionId = DB::table('permissions')->where('name', self::PERMISSION)->value('id');

            if ($permissionId !== null) {
                DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
                DB::table('permissions')->where('id', $permissionId)->delete();
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    /**
     * Owners and managers record shop-to-shop trades on tenants seeded
     * before the permission existed.
     */
    private function grantPermission(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        $permissionId = DB::table('permissions')
            ->where('name', self::PERMISSION)
            ->where('guard_name', 'tenant')
            ->value('id') ?? DB::table('permissions')->insertGetId([
                'name' => self::PERMISSION,
                'guard_name' => 'tenant',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $roleIds = DB::table('roles')
            ->whereIn('name', ['owner', 'manager'])
            ->where('guard_name', 'tenant')
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
