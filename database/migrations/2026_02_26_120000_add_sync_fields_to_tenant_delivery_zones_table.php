<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection('central')->table('tenant_delivery_zones', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_zone_id')->nullable()->after('tenant_id');
            $table->timestamp('last_synced_at')->nullable()->after('is_active');
            $table->string('sync_status')->nullable()->after('last_synced_at');

            $table->unique(['tenant_id', 'tenant_zone_id'], 'tenant_delivery_zones_tenant_zone_unique');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('tenant_delivery_zones', function (Blueprint $table) {
            $table->dropUnique('tenant_delivery_zones_tenant_zone_unique');
            $table->dropColumn(['tenant_zone_id', 'last_synced_at', 'sync_status']);
        });
    }
};
