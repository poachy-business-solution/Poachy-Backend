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
        Schema::table('sale_refunds', function (Blueprint $table) {
            $table->string('status')->default('processing')->after('processed_by');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null')->after('status');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->timestamp('processed_at')->nullable()->after('approved_at');
            $table->foreignId('exchange_sale_id')->nullable()->constrained('sales')->onDelete('set null')->after('processed_at');

            $table->index(['status']);
            $table->index(['exchange_sale_id']);
        });
    }

    public function down(): void
    {
        Schema::table('sale_refunds', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['exchange_sale_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['exchange_sale_id']);
            $table->dropColumn(['status', 'approved_by', 'approved_at', 'processed_at', 'exchange_sale_id']);
        });
    }
};
