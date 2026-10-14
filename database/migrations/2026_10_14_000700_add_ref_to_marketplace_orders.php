<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Link attribution on orders (own link / advert / marketplace), as on reservations. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->table('marketplace_orders', function (Blueprint $table) {
            $table->string('ref', 20)->nullable()->after('customer_notes');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('marketplace_orders', function (Blueprint $table) {
            $table->dropColumn('ref');
        });
    }
};
