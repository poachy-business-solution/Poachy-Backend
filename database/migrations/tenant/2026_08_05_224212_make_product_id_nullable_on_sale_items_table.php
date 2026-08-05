<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A bundle sale item is stored with product_id = null / bundle_id = set — every
     * consumer already handles this (SaleItem::getDisplayNameAttribute() checks
     * bundle_id first, SalesDailyAggregateService::determineSellableData() returns
     * early for bundles without touching ->product), but product_id was left as a
     * NOT NULL FK, so SaleService::createSaleItems() throws a DB integrity violation
     * on every bundle sale. This was the missing piece, not an app-logic bug.
     */
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable(false)->change();
        });
    }
};
