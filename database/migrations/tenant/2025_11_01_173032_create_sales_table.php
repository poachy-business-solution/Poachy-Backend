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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('sale_number')->unique(); // e.g., "INV-2025-000001"
            $table->foreignId('store_id')->constrained('stores')->onDelete('restrict');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null'); // NULL for walk-ins
            $table->timestamp('sale_date')->useCurrent();
            
            // Amounts
            $table->decimal('subtotal', 15, 2); // Before tax and discount
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2); // Final amount
            
            // Payment
            $table->string('payment_status')->default('paid'); // paid, partially_paid, unpaid
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('amount_due', 15, 2)->default(0); // For credit sales
            $table->string('payment_method')->default('cash'); // cash, bank_transfer, mpesa, card, credit, mixed, other
            $table->string('payment_reference')->nullable(); // MPESA code, card approval, etc.
            
            // Coupon & Loyalty
            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->onDelete('set null');
            $table->decimal('loyalty_points_earned', 15, 2)->default(0);
            $table->decimal('loyalty_points_redeemed', 15, 2)->default(0);
            
            // Staff
            $table->foreignId('served_by')->constrained('users')->onDelete('restrict'); // Cashier
            
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['store_id', 'sale_date']);
            $table->index(['customer_id']);
            $table->index(['sale_number']);
            $table->index(['payment_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
