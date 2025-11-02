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
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->string('budget_name'); // e.g., "Q1 2025 Marketing Budget"
            $table->foreignId('store_id')->nullable()->constrained('stores')->onDelete('cascade'); // NULL = company-wide
            $table->foreignId('category_id')->constrained('expense_categories')->onDelete('restrict');
            
            // Period
            $table->string('period_type'); // monthly, quartely, yearly, custom
            $table->date('period_start');
            $table->date('period_end');
            
            // Budget amounts
            $table->decimal('budget_amount', 15, 2); // Allocated budget
            $table->decimal('spent_amount', 15, 2)->default(0); // Computed from expenses
            $table->decimal('remaining_amount', 15, 2)->default(0); // budget_amount - spent_amount
            $table->decimal('committed_amount', 15, 2)->default(0); // Pending/approved expenses
            
            // Alerts
            $table->decimal('alert_threshold_percentage', 5, 2)->default(80); // Alert at 80% spent
            $table->boolean('alert_triggered')->default(false);
            $table->timestamp('alert_triggered_at')->nullable();
            
            // Status
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();
            
            $table->index(['store_id', 'period_start', 'period_end']);
            $table->index(['category_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
