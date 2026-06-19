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
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "Rent", "Utilities", "Salaries"
            $table->string('code')->unique(); // e.g., "RENT", "UTIL", "SAL"
            $table->text('description')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('expense_categories')->onDelete('set null'); // For subcategories
            // Example: "Utilities" → "Electricity", "Water", "Internet"
            $table->boolean('is_recurring_eligible')->default(false); // Can be set as recurring expense
            $table->boolean('requires_receipt')->default(false); // Must attach receipt
            $table->boolean('requires_approval')->default(false); // Needs manager approval
            $table->boolean('is_active')->default(true);
            $table->integer('display_order')->default(0); // For sorting
            $table->timestamps();
            
            $table->index(['parent_id', 'is_active']);
            $table->index('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
