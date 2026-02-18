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
        Schema::connection('central')->table('marketplace_orders', function (Blueprint $table) {
            $table->string('checkout_idempotency_key')->nullable()->after('cancelled_by');
            $table->index('checkout_idempotency_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->table('marketplace_orders', function (Blueprint $table) {
            $table->dropIndex('marketplace_orders_checkout_idempotency_key_index');
            $table->dropColumn('checkout_idempotency_key');
        });
    }
};
