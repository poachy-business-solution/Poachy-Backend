<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('central_payment_logs', function (Blueprint $table) {
            $table->id();

            // Polymorphic reference to the related payment record
            $table->string('payable_type', 50);          // 'marketplace_order_payment' | 'subscription_payment'
            $table->unsignedBigInteger('payable_id');

            // What happened
            $table->string('event', 80);                  // e.g. 'stk_initiated', 'c2b_confirmation_received', 'payment_completed', 'subscription_activated'

            // Context: one or both will be set depending on payment type
            $table->string('tenant_id', 36)->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();

            // Payment details
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('customer_phone', 20)->nullable();

            // M-Pesa references
            $table->string('transaction_reference', 100)->nullable(); // CheckoutRequestID (STK) or TransID (C2B)
            $table->string('provider_reference', 100)->nullable();     // M-Pesa receipt number

            // Response data
            $table->string('result_code', 20)->nullable();
            $table->text('result_description')->nullable();

            // Raw payload (redact any PAN/sensitive fields before storing)
            $table->json('raw_payload')->nullable();

            $table->string('ip_address', 45)->nullable();

            // Append-only — no updated_at, no soft deletes
            $table->timestamp('created_at')->useCurrent();

            $table->index(['payable_type', 'payable_id']);
            $table->index(['event', 'created_at']);
            $table->index('transaction_reference');
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('central_payment_logs');
    }
};
