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
        Schema::table('sale_refund_items', function (Blueprint $table) {
            $table->dropForeign(['refund_id']);
            $table->foreign('refund_id')->references('id')->on('sale_refunds')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('sale_refund_items', function (Blueprint $table) {
            $table->dropForeign(['refund_id']);
            $table->foreign('refund_id')->references('id')->on('sale_refunds')->onDelete('restrict');
        });
    }
};
