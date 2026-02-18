<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_sales', function (Blueprint $table) {
            $table->id();

            // Link back to the central marketplace order for cross-reference
            $table->unsignedBigInteger('central_order_id');
            $table->string('sale_number')->unique(); // e.g. "MKT-ORD-2025-000001"

            // The tenant store fulfilling this order (determined from inventory reservation)
            $table->foreignId('store_id')->constrained('stores')->onDelete('restrict');

            $table->timestamp('sale_date')->useCurrent();

            // Amounts
            $table->decimal('subtotal', 15, 2);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);

            // Payment
            $table->string('payment_status')->default('paid'); // paid, pending
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('amount_due', 15, 2)->default(0);
            $table->string('payment_method');
            $table->string('payment_reference')->nullable(); // M-Pesa transaction ref, COD ref, etc.

            // Fulfillment
            $table->string('fulfillment_type')->default('delivery'); // delivery, pickup

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('central_order_id');
            $table->index('sale_number');
            $table->index('payment_status');
            $table->index(['store_id', 'sale_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_sales');
    }
};
