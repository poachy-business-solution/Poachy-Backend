<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('central')->table('shopping_carts', function (Blueprint $table) {
            $table->dropForeign('shopping_carts_tenant_id_foreign');
            $table->dropIndex('shopping_carts_tenant_id_status_index');
            $table->dropColumn('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->table('shopping_carts', function (Blueprint $table) {
            $table->string('tenant_id')->after('session_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index(['tenant_id', 'status']);
        });
    }
};
