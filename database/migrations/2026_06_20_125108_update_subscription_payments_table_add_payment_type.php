<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->table('subscription_payments', function (Blueprint $table) {
            // How the payment was initiated: 'stk' (app-triggered push) or 'c2b' (tenant pays via Paybill menu)
            $table->string('payment_type', 10)->default('stk')->after('payment_status');

            // BillRefNumber from C2B confirmation (the tenant account number, e.g. POA00001)
            $table->string('bill_ref_number', 30)->nullable()->after('payment_type');

            // Rename phone_number → customer_phone for clarity (C2B: phone comes from MSISDN in callback)
            $table->renameColumn('phone_number', 'customer_phone');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('subscription_payments', function (Blueprint $table) {
            $table->renameColumn('customer_phone', 'phone_number');
            $table->dropColumn(['payment_type', 'bill_ref_number']);
        });
    }
};
