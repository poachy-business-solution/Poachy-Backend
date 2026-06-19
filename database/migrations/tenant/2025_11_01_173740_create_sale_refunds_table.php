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
        Schema::create('sale_refunds', function (Blueprint $table) {
            $table->id();
            $table->string('refund_number')->unique(); // e.g., "REF-2025-001"
            $table->foreignId('original_sale_id')->constrained('sales')->onDelete('restrict');
            $table->foreignId('store_id')->constrained('stores')->onDelete('restrict');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null');
            $table->date('refund_date');
            $table->decimal('refund_amount', 15, 2);
            $table->string('refund_method'); // cash, mpesa, card_reversal, store_credit, exchange
            $table->string('reason'); // defective, wrong_item, expired, customer_changed_mind, other
            $table->text('notes')->nullable();
            $table->foreignId('processed_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();
            
            $table->index(['original_sale_id']);
            $table->index(['refund_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_refunds');
    }
};
