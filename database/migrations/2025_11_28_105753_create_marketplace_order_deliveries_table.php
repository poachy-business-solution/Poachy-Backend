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
        Schema::connection('central')->create('marketplace_order_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('marketplace_orders')->onDelete('restrict');
            // One delivery record per order

            // Delivery Method
            $table->string('delivery_method')->default('standard'); // standard, express, scheduled

            // Status Tracking
            $table->string('delivery_status')->default('pending');
            // pending, confirmed, assigned, picked_up, in_transit, out_for_delivery, delivered, failed, returned

            // Courier Information
            $table->string('courier_company')->nullable(); // "Sendy", "Glovo", "Bolt"
            $table->string('courier_name')->nullable(); // Driver name
            $table->string('courier_phone')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('tracking_url')->nullable();

            // Timing
            $table->timestamp('estimated_pickup_time')->nullable();
            $table->timestamp('actual_pickup_time')->nullable();
            $table->timestamp('estimated_delivery_time')->nullable();
            $table->timestamp('actual_delivery_time')->nullable();

            // Delivery Proof
            $table->string('delivery_proof_type')->nullable(); // signature, photo, otp
            $table->text('delivery_proof_data')->nullable(); // Signature image, photo URL, OTP
            $table->string('received_by_name')->nullable();
            $table->string('received_by_phone')->nullable();

            // Issues
            $table->text('delivery_notes')->nullable();
            $table->text('delivery_issues')->nullable(); // "Customer not home", "Wrong address"
            $table->integer('delivery_attempts')->default(0);

            // Location Tracking (last known position)
            $table->decimal('last_latitude', 10, 8)->nullable();
            $table->decimal('last_longitude', 11, 8)->nullable();
            $table->timestamp('last_location_update')->nullable();

            $table->timestamps();

            $table->index(['delivery_status', 'estimated_delivery_time'], 'idx_status_delivery');
            $table->index('tracking_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->dropIfExists('marketplace_order_deliveries');
    }
};
