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
        Schema::create('marketplace_sale_item_batch_depletions', function (Blueprint $table) {
            $table->id();

            // Default auto-generated FK constraint names exceed MySQL's 64-char
            // identifier limit on this table/column combination — named explicitly.
            $table->foreignId('marketplace_sale_item_id');
            $table->foreign('marketplace_sale_item_id', 'mkt_sale_item_batch_depl_sale_item_fk')
                ->references('id')->on('marketplace_sale_items')->onDelete('restrict');

            $table->foreignId('product_id')->constrained('products')->onDelete('restrict');
            $table->foreignId('batch_id')->constrained('product_batches')->onDelete('restrict');
            $table->decimal('quantity_in_base_uom', 15, 4);
            $table->timestamps();

            $table->index(['marketplace_sale_item_id'], 'mkt_sale_item_batch_depl_sale_item_idx');
            $table->index(['batch_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketplace_sale_item_batch_depletions');
    }
};
