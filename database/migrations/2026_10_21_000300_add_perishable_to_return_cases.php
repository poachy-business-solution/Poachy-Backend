<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item 51: perishables can be returned, on short clocks (asked within 24 h of handover, brought
 * back within 24 h of asking) and only when something was wrong on arrival.
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection('central')->table('marketplace_return_cases', function (Blueprint $table) {
            $table->boolean('perishable')->default(false)->after('shop_at_fault');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('marketplace_return_cases', function (Blueprint $table) {
            $table->dropColumn('perishable');
        });
    }
};
