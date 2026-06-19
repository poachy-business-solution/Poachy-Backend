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
        Schema::table('units_of_measure', function (Blueprint $table) {
            $table->string('source_type')->default('system')->after('type');
            // Possible values: 'system', 'custom'

            $table->index('source_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unit_of_measures', function (Blueprint $table) {
            $table->dropIndex(['source_type']);
            $table->dropColumn('source_type');
        });
    }
};
