<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSION = 'record-expenses';

    /**
     * Money leaving or entering the till drawer outside a sale:
     * - expenses say what they were paid from; till-cash expenses attach to
     *   the till session and reduce its expected cash;
     * - cash movements record float top-ups (in), owner drawings and
     *   suppliers paid from the till (out);
     * - cashiers may record expenses (owner approves): new permission.
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('paid_from', 20)->nullable()->after('payment_method');
            $table->foreignId('shift_assignment_id')->nullable()->after('store_id')
                ->constrained('shift_assignments')->nullOnDelete();
        });

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('shift_assignment_id')->nullable()->constrained('shift_assignments')->nullOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('direction', 3);
            $table->string('reason', 30);
            $table->decimal('amount', 15, 2);
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['store_id', 'occurred_at']);
            $table->index('shift_assignment_id');
        });

        $this->grantPermission();
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shift_assignment_id');
            $table->dropColumn('paid_from');
        });

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
     * Owner, manager and cashier may record expenses and drawer cash on
     * tenants seeded before the permission existed.
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
            ->whereIn('name', ['owner', 'manager', 'cashier'])
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
