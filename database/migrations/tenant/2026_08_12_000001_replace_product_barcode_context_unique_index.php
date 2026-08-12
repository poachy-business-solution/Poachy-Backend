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
        Schema::table('product_barcodes', function (Blueprint $table) {
            $table->dropUnique('unique_active_barcode_context');
            $table->index(['barcode', 'store_id', 'supplier_id', 'is_active'], 'idx_barcode_context_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_barcodes', function (Blueprint $table) {
            $table->dropIndex('idx_barcode_context_status');
            $table->unique(
                ['barcode', 'store_id', 'supplier_id', 'is_active'],
                'unique_active_barcode_context'
            );
        });
    }
};
