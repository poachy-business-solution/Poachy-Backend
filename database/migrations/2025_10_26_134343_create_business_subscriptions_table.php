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
        Schema::connection('central')->create('business_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('subscription_plan_id')->constrained('subscription_plans');
            
            // Subscription Period
            $table->date('start_date');
            $table->date('end_date')->nullable(); // Null for lifetime/free plans
            
            // Payment Information
            $table->decimal('amount_paid', 10, 2)->default(0.00);
            $table->string('currency')->default('KES');
            $table->string('payment_method')->nullable(); // mpesa, card, bank_transfer, etc.
            $table->string('payment_reference')->nullable(); // Transaction ID
            $table->timestamp('payment_date')->nullable();
            
            // Status
            $table->enum('status', ['active', 'expired', 'cancelled', 'pending', 'trial'])->default('pending');
            $table->boolean('auto_renew')->default(false);
            
            // Trial Information
            $table->boolean('is_trial')->default(false);
            $table->date('trial_ends_at')->nullable();
            
            // Cancellation
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            
            $table->timestamps();
            
            // Foreign Keys
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            
            // Indexes
            $table->index('tenant_id');
            $table->index(['tenant_id', 'status']);
            $table->index(['start_date', 'end_date']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->dropIfExists('business_subscriptions');
    }
};
