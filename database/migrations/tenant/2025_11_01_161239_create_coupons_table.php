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
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // e.g., "SAVE20", "WELCOME10"
            $table->string('description');
            $table->string('discount_type'); // percentage, fixed_amount
            $table->decimal('discount_value', 15, 2); // 20 (for 20%) or 500 (for KES 500 off)
            $table->decimal('min_purchase_amount', 15, 2)->nullable(); // Minimum spend required
            $table->decimal('max_discount_amount', 15, 2)->nullable(); // Cap for percentage discounts
            $table->integer('usage_limit')->nullable(); // Total times it can be used
            $table->integer('usage_count')->default(0); // Times already used
            $table->integer('usage_limit_per_customer')->nullable();
            $table->date('valid_from');
            $table->date('valid_until');
            $table->string('applicable_to'); // all_products, specific_categories, specific_products, specific_brands
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['code', 'is_active']);
            $table->index(['valid_from', 'valid_until']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
