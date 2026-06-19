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
        Schema::connection('central')->create('marketplace_order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('marketplace_orders')->onDelete('restrict');

            // Payment Details
            $table->string('payment_method'); // mpesa, card, cash_on_delivery, bank_transfer
            $table->string('payment_provider')->nullable(); // Pesapal, Flutterwave, Stripe
            $table->decimal('amount', 15, 2);

            // Status
            $table->string('payment_status')->default('pending'); // pending, processing, completed, failed, refunded

            // References
            $table->string('transaction_reference')->nullable(); // M-PESA code, card approval
            $table->string('provider_reference')->nullable(); // Gateway transaction ID

            // Timestamps
            $table->timestamp('initiated_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            // Error tracking
            $table->text('failure_reason')->nullable();
            $table->string('failure_code')->nullable();

            // Refund tracking
            $table->boolean('is_refunded')->default(false);
            $table->decimal('refunded_amount', 15, 2)->default(0);
            $table->timestamp('refunded_at')->nullable();
            $table->string('refund_reference')->nullable();

            // Metadata
            $table->json('payment_metadata')->nullable(); // Additional gateway data

            $table->timestamps();
            $table->softDeletes();

            $table->index(['order_id', 'payment_status']);
            $table->index(['payment_status', 'completed_at']);
            $table->index('transaction_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->connection('central')->dropIfExists('marketplace_order_payments');
    }
};
