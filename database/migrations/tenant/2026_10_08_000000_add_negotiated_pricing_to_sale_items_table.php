<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Negotiated pricing, giveaways and open items (docs/pos-negotiated-pricing.md).
     *
     * `unit_price` keeps meaning "price actually charged"; `list_unit_price` keeps
     * the catalogue floor at time of sale so negotiation uplift stays reportable
     * after prices change. Open items have no product and no UOM, and giveaways /
     * open items carry no tax, so `uom_id` and `tax_rate_id` become nullable —
     * the calculation service already emits a null tax rate for untaxed products,
     * which the NOT NULL column rejected at insert.
     */
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('list_unit_price', 15, 2)->nullable()->after('unit_price');
            $table->boolean('is_giveaway')->default(false)->after('list_unit_price');
            $table->string('price_override_reason')->nullable()->after('is_giveaway');
            $table->string('description')->nullable()->after('bundle_id');
            $table->unsignedBigInteger('uom_id')->nullable()->change();
            $table->unsignedBigInteger('tax_rate_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn(['list_unit_price', 'is_giveaway', 'price_override_reason', 'description']);
            $table->unsignedBigInteger('uom_id')->nullable(false)->change();
            $table->unsignedBigInteger('tax_rate_id')->nullable(false)->change();
        });
    }
};
