<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item 48: no VAT is added for now. A shop that is VAT-registered switches this on; until then
 * neither its till nor its online orders add VAT on top of prices.
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection('central')->table('business_details', function (Blueprint $table) {
            $table->boolean('charge_vat')->default(false)->after('return_window_days');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('business_details', function (Blueprint $table) {
            $table->dropColumn('charge_vat');
        });
    }
};
