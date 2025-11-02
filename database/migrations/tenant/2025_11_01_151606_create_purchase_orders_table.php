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
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();

            // Purchase order details
            $table->string('po_number')->unique(); // e.g., "PO-2025-001"
            $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('restrict');
            $table->foreignId('store_id')->constrained('stores')->onDelete('restrict'); // Which location is ordering
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->string('status')->default('draft'); // draft, sent, confirmed, partially_received, received, cancelled
            
            // Costs
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('shipping_cost', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            
            // Payment settlement
            $table->string('payment_status')->default('unpaid'); // unpaid, partially_paid, paid
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->text('notes')->nullable();
            
            //  Audit trail
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('restrict');
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['supplier_id', 'status']);
            $table->index(['store_id', 'order_date']);
            $table->index('po_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
