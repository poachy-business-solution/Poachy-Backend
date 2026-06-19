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
        Schema::connection('central')->create('marketplace_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique(); // e.g., "MKT-ORD-2025-000001"

            // Customer
            $table->foreignId('customer_id')->constrained('marketplace_customers')->onDelete('restrict');
            $table->foreignId('delivery_address_id')->nullable()->constrained('customer_addresses')->onDelete('restrict');
            // NULL = customer pickup from store

            // Merchant
            $table->string('tenant_id');
            $table->string('merchant_name'); // Denormalized for display
            $table->unsignedBigInteger('tenant_store_id')->nullable(); // Specific store location for pickup

            // Order Amounts
            $table->decimal('subtotal', 15, 2);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('delivery_fee', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);

            // Fulfillment Type
            $table->string('fulfillment_type')->default('delivery'); // delivery, pickup

            // Status
            $table->string('order_status')->default('pending');
            // pending, confirmed, processing, ready_for_pickup, out_for_delivery, completed, cancelled, refunded

            $table->text('customer_notes')->nullable();
            $table->text('merchant_notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('marketplace_customers')->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();

            // Foreign Keys
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');

            // Indexes
            $table->index(['customer_id', 'created_at']);
            $table->index(['tenant_id', 'order_status']);
            $table->index('order_number');
            $table->index('fulfillment_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->dropIfExists('marketplace_orders');
    }
};
