<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection('central')->table('marketplace_order_deliveries', function (Blueprint $table) {
            $table->unsignedBigInteger('zone_id')->nullable()->after('delivery_method');
            $table->string('zone_name')->nullable()->after('zone_id');
            $table->decimal('delivery_fee', 10, 2)->nullable()->after('zone_name');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('marketplace_order_deliveries', function (Blueprint $table) {
            $table->dropColumn(['zone_id', 'zone_name', 'delivery_fee']);
        });
    }
};
