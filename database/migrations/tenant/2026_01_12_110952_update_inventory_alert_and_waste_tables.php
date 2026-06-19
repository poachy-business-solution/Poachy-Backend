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
        Schema::table('stock_alerts', function (Blueprint $table) {
            // Add variant support
            $table->foreignId('product_variant_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_variants')
                ->onDelete('cascade');

            // Add resolved_by tracking
            $table->foreignId('resolved_by')
                ->nullable()
                ->after('resolved_at')
                ->constrained('users')
                ->onDelete('set null');
        });

        Schema::table('expiry_alerts', function (Blueprint $table) {
            // Add resolved_by tracking
            $table->foreignId('resolved_by')
                ->nullable()
                ->after('resolved_at')
                ->constrained('users')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
