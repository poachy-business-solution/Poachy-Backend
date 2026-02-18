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
        Schema::connection('central')->table('marketplace_order_payments', function (Blueprint $table) {
            $table->dropIndex('marketplace_order_payments_transaction_reference_index');
            $table->unique('transaction_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->table('marketplace_order_payments', function (Blueprint $table) {
            $table->dropUnique('marketplace_order_payments_transaction_reference_unique');
            $table->index('transaction_reference');
        });
    }
};
