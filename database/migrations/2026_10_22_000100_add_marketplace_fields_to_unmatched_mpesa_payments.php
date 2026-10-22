<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Marketplace money that matched no open order (short payment, a second payment, an unknown
        // order number, a second STK prompt paid). Finance sends it back by M-Pesa reversal and marks
        // it returned; it is never credited to a shop's billing account.
        Schema::connection('central')->table('unmatched_mpesa_payments', function (Blueprint $table) {
            $table->string('source', 12)->default('paybill')->after('transaction_id');   // paybill | marketplace
            $table->string('order_number', 40)->nullable()->after('bill_ref_number');
            $table->string('note', 255)->nullable()->after('payer_name');
            $table->string('returned_by', 64)->nullable()->after('assigned_at');
            $table->timestamp('returned_at')->nullable()->after('returned_by');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('unmatched_mpesa_payments', function (Blueprint $table) {
            $table->dropColumn(['source', 'order_number', 'note', 'returned_by', 'returned_at']);
        });
    }
};
