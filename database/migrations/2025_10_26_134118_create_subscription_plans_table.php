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
        Schema::connection('central')->create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "Free", "Basic", "Premium", "Enterprise"
            $table->string('slug')->unique(); // e.g., "free", "basic", "premium"
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0.00); // Monthly price
            $table->string('currency')->default('KES');
            $table->integer('billing_cycle_days')->default(30); // 30 days, 365 days, etc.
            
            // Feature Limits (JSON for flexibility)
            $table->json('features')->nullable(); 
            
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
            
            $table->index('slug');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->dropIfExists('subscription_plans');
    }
};
