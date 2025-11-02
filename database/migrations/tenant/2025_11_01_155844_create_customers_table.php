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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_number')->unique(); // e.g., "CUST-000001"
            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->string('phone')->unique();
            $table->date('date_of_birth')->nullable(); // For birthday promotions
            $table->text('address')->nullable();
            $table->string('customer_type')->default('walk_in'); // walk-in, regular, vip, wholesale
            
            // Loyalty Program
            $table->decimal('loyalty_points', 15, 2)->default(0); // Current balance
            $table->decimal('total_lifetime_purchases', 15, 2)->default(0); // Aggregate revenue
            $table->integer('total_visits')->default(0); // Count of transactions
            $table->foreignId('preferred_store_id')->nullable()->constrained('stores')->onDelete('set null');
            
            // Credit Management
            $table->decimal('credit_limit', 15, 2)->default(0); // For credit customers
            $table->decimal('current_debt', 15, 2)->default(0); // Amount owed
            
            $table->boolean('is_active')->default(true);
            $table->timestamp('registered_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['phone']);
            $table->index(['email']);
            $table->index(['customer_type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
