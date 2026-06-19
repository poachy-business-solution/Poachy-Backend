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
        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('restrict');
            $table->string('transaction_type'); // earned, redeemed, expired, adjusted, bonus
            $table->decimal('points', 15, 2); // Positive for earned, negative for redeemed
            $table->decimal('balance_after', 15, 2); // Points balance after this transaction
            $table->string('reference_type')->nullable(); // e.g., "Sale", "Promotion", "Manual"
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('description')->nullable();
            $table->date('expires_at')->nullable(); // Points expiry date
            $table->softDeletes();
            $table->timestamps();
            
            $table->index(['customer_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_transactions');
    }
};
