<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Paybill payments naming a shop account that doesn't exist (Safaricom skipped validation).
        // Staff assign each to the right shop by hand; the receipt can only be credited once.
        Schema::connection('central')->create('unmatched_mpesa_payments', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id', 40)->unique();
            $table->decimal('amount', 12, 2);
            $table->string('bill_ref_number', 40)->nullable();
            $table->string('phone_masked', 20)->nullable();
            $table->string('payer_name', 120)->nullable();
            $table->string('status', 10)->default('open');      // open | assigned
            $table->string('assigned_tenant_id', 36)->nullable();
            $table->unsignedBigInteger('billing_ledger_id')->nullable();
            $table->string('assigned_by', 64)->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('unmatched_mpesa_payments');
    }
};
