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
        Schema::create('customer_credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('restrict');
            $table->string('transaction_type'); // sale_on_credit, payment, adjustment, write_off
            $table->decimal('amount', 15, 2); // Positive for debt, negative for payment
            $table->decimal('balance_after', 15, 2); // Customer's debt after transaction
            $table->string('reference_type')->nullable(); // e.g., "Sale", "Payment"
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('payment_method')->nullable(); // cash, bank_transfer, cheque, mpesa, card, other
            $table->string('payment_reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['customer_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_credit_transactions');
    }
};
