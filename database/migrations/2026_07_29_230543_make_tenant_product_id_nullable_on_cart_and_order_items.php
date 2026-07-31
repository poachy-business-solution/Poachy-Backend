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
        Schema::connection('central')->table('shopping_cart_items', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_product_id')->nullable()->change();
        });

        Schema::connection('central')->table('marketplace_order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_product_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->table('shopping_cart_items', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_product_id')->nullable(false)->change();
        });

        Schema::connection('central')->table('marketplace_order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_product_id')->nullable(false)->change();
        });
    }
};
