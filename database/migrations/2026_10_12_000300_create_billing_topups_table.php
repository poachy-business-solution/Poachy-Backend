<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // STK top-up requests (Paybill C2B top-ups go straight to the ledger).
        Schema::connection('central')->create('billing_topups', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36);
            $table->decimal('amount', 12, 2);
            $table->string('phone', 20);
            $table->string('status', 12);                       // pending | processing | completed | failed
            $table->string('checkout_request_id', 100)->nullable()->unique();
            $table->string('merchant_request_id', 100)->nullable();
            $table->string('mpesa_receipt', 40)->nullable();
            $table->string('failure_reason')->nullable();
            $table->string('idempotency_key', 160)->nullable()->unique(); // "{tenant}:{client key}"
            $table->string('initiated_by', 64)->nullable();     // tenant user id, "pay_link:{id}", "admin:{id}"
            $table->foreignId('payment_link_id')->nullable()->constrained('payment_links')->nullOnDelete();
            $table->foreignId('billing_ledger_id')->nullable()->constrained('billing_ledger')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('billing_topups');
    }
};
