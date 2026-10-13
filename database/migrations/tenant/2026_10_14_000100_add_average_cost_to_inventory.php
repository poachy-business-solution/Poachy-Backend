<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Moving weighted average of what the stock on hand cost, per branch. Updated on every
        // costed receipt so stock bought at mixed prices gives the right profit on each sale.
        Schema::table('inventory', function (Blueprint $table) {
            $table->decimal('average_cost', 15, 4)->nullable()->after('quantity_damaged');
        });
    }

    public function down(): void
    {
        Schema::table('inventory', fn (Blueprint $table) => $table->dropColumn('average_cost'));
    }
};
