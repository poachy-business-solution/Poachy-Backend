<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Item 49: payout processing fees, hold and minimum are set by Poachy finance in the admin, from
 * a date on (like commission rates and billing prices) instead of fixed config values.
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection('central')->create('marketplace_fee_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('mpesa_fee_percent', 5, 2);
            $table->decimal('card_fee_percent', 5, 2);
            $table->decimal('transfer_fee', 10, 2)->default(0);
            $table->unsignedSmallInteger('hold_hours')->default(24);
            $table->unsignedInteger('min_payout')->default(10);
            $table->date('effective_from')->index();
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        DB::connection('central')->table('marketplace_fee_settings')->insert([
            'mpesa_fee_percent' => config('payouts.fees.mpesa_percent', 0.55),
            'card_fee_percent' => config('payouts.fees.card_percent', 2.9),
            'transfer_fee' => config('payouts.fees.transfer_fee', 0),
            'hold_hours' => config('payouts.hold_hours', 24),
            'min_payout' => config('payouts.min_amount', 10),
            'effective_from' => '2026-01-01',
            'notes' => 'Launch defaults (replace with the real Daraja / Paystack tariffs)',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('marketplace_fee_settings');
    }
};
