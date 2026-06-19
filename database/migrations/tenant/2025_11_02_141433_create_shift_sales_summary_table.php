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
        Schema::create('shift_sales_summary', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('shift_assignment_id')->unique()->constrained('shift_assignments')->onDelete('restrict');
            
            // Sales metrics
            $table->integer('total_transactions')->default(0);
            $table->decimal('total_sales_amount', 15, 2)->default(0);
            $table->decimal('total_cash_sales', 15, 2)->default(0);
            $table->decimal('total_card_sales', 15, 2)->default(0);
            $table->decimal('total_mpesa_sales', 15, 2)->default(0);
            $table->decimal('total_credit_sales', 15, 2)->default(0);
            
            // Returns/refunds during shift
            $table->integer('total_refunds')->default(0);
            $table->decimal('total_refund_amount', 15, 2)->default(0);
            
            // Discounts given
            $table->decimal('total_discounts_given', 15, 2)->default(0);
            
            // Unique customers served
            $table->integer('unique_customers')->default(0);
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_sales_summary');
    }
};
