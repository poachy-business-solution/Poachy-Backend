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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_number')->unique(); // e.g., "EXP-2025-001"
            $table->foreignId('store_id')->nullable()->constrained('stores')->onDelete('restrict'); // NULL = company-wide expense
            $table->foreignId('category_id')->constrained('expense_categories')->onDelete('restrict');
            
            // Amount
            $table->decimal('amount', 15, 2);
            $table->text('description');
            $table->date('expense_date');
            
            // Payment
            $table->string('payment_method'); // cash, bank_transfer, mpesa, cheque, card, other
            $table->string('payment_reference')->nullable(); // Transaction ref, cheque number
            $table->string('payment_status')->default('pending'); // pending, paid, overdue
            
            // Receipt
            $table->string('receipt_path')->nullable(); // File storage path
            $table->string('receipt_number')->nullable(); // Physical receipt number
            
            // Recurring
            $table->boolean('is_recurring')->default(false);
            $table->string('recurrence_frequency')->nullable(); // daily, weekly, monthly, quartely, yearly
            $table->integer('recurrence_interval')->default(1); // Every X periods (e.g., every 2 months)
            $table->date('recurrence_start_date')->nullable();
            $table->date('recurrence_end_date')->nullable(); // NULL = indefinite
            $table->date('next_occurrence_date')->nullable(); // When to generate next expense
            $table->foreignId('parent_expense_id')->nullable()->constrained('expenses')->onDelete('set null'); // Link recurring instances
            
            // Supplier (if paid to supplier)
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->onDelete('set null');
            
            // Approval
            $table->string('approval_status')->default('approved'); // pending, approved, rejected
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('restrict');
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['store_id', 'expense_date']);
            $table->index(['category_id', 'expense_date']);
            $table->index(['expense_number']);
            $table->index(['is_recurring', 'next_occurrence_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
