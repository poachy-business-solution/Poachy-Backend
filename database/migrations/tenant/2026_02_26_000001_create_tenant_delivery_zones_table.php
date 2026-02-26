<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_delivery_zones', function (Blueprint $table) {
            $table->id();

            $table->string('zone_name');
            $table->enum('zone_type', ['city', 'county', 'postal_code', 'radius'])->default('city');

            // Zone matching criteria — one set used based on zone_type
            $table->json('cities')->nullable();
            $table->json('counties')->nullable();
            $table->json('postal_codes')->nullable();

            // Radius-based zone
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->unsignedInteger('radius_km')->nullable();

            // Delivery method fees
            $table->decimal('standard_fee', 10, 2)->default(0);
            $table->decimal('express_fee', 10, 2)->nullable();
            $table->decimal('scheduled_fee', 10, 2)->nullable();

            // Free delivery threshold (zone-specific)
            $table->decimal('free_delivery_threshold', 10, 2)->nullable();

            // Estimated delivery times
            $table->string('standard_delivery_time')->nullable();
            $table->string('express_delivery_time')->nullable();
            $table->string('scheduled_delivery_time')->nullable();

            // Supported methods for this zone (e.g. ["standard", "express"])
            $table->json('supported_methods')->nullable();

            // Lower number = higher priority when zones overlap
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['is_active', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_delivery_zones');
    }
};
