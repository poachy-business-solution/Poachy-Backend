<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('subscription_plan_id')->constrained('subscription_plans');

            // The phone number that received the STK push
            $table->string('phone_number');
            $table->decimal('amount', 10, 2);

            // pending → processing (STK sent) → completed | failed
            $table->string('payment_status')->default('pending');

            // Daraja CheckoutRequestID stored here while awaiting callback
            $table->string('transaction_reference')->nullable();

            // M-Pesa receipt number (set on success callback)
            $table->string('provider_reference')->nullable();

            $table->timestamp('initiated_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->string('failure_code')->nullable();

            $table->json('payment_metadata')->nullable();

            // Linked subscription record created on successful payment
            $table->foreignId('business_subscription_id')
                ->nullable()
                ->constrained('business_subscriptions');

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->index('transaction_reference');
            $table->index(['tenant_id', 'payment_status']);
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('subscription_payments');
    }
};
