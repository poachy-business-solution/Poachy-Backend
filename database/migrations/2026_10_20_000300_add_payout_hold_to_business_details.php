<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Item 41: Poachy can hold a shop's marketplace payouts (fraud check, disputed account, wrong number). */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection('central')->table('business_details', function (Blueprint $table) {
            $table->timestamp('payouts_held_at')->nullable()->after('payout_phone');
            $table->string('payouts_hold_reason', 500)->nullable()->after('payouts_held_at');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('business_details', fn (Blueprint $table) => $table->dropColumn(['payouts_held_at', 'payouts_hold_reason']));
    }
};
