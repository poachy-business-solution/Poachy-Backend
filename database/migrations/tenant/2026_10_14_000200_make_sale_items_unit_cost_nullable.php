<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A line whose buying price nobody knows is recorded as NULL, not 0, so profit figures can
        // say "cost unknown — add a buying price" instead of silently counting the whole sale as profit.
        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 15, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 15, 2)->nullable(false)->default(0)->change();
        });
    }
};
