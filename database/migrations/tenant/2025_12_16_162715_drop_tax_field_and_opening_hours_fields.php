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
        // Drop tax_id index and column from suppliers table
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropIndex(['tax_id']);
            $table->dropColumn('tax_id');
        });

        // Drop opening_hours from stores table
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('opening_hours');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore tax_id column and index to suppliers table
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('tax_id')->nullable()->after('payment_terms');
            $table->index('tax_id');
        });

        // Restore opening_hours to stores table
        Schema::table('stores', function (Blueprint $table) {
            $table->json('opening_hours')->nullable()->after('is_active');
        });
    }
};
