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
        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number')->unique(); // e.g., "PAY-SUP-2025-001"
            $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('restrict');
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->onDelete('restrict'); // Can be general payment
            $table->date('payment_date');
            $table->decimal('amount', 15, 2);
            $table->string('payment_method'); // cash, bank_transfer, cheque, mpesa, card, other
            $table->string('reference_number')->nullable(); // Transaction ref, cheque number, etc.
            $table->text('notes')->nullable();
            $table->string('receipt_path')->nullable(); // File storage path
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('restrict');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            
            $table->index(['supplier_id', 'payment_date']);
            $table->index(['purchase_order_id']);
            $table->index('payment_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
    }
};
