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
        Schema::table('inventory_waste', function (Blueprint $table) {
            $table->foreignId('serial_id')->nullable()->after('batch_id')->constrained('product_serials')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_waste', function (Blueprint $table) {
            $table->dropConstrainedForeignId('serial_id');
        });
    }
};
