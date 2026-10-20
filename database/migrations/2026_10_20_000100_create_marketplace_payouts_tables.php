<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Item 37: what Poachy owes each shop (one ledger line per handed-over order, plus clawbacks
 * and adjustments), the nightly B2C payouts that settle it, and the commission rates (0%).
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection('central')->create('marketplace_commission_rates', function (Blueprint $table) {
            $table->id();
            $table->decimal('rate_percent', 5, 2);
            $table->date('effective_from');
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->index('effective_from');
        });

        DB::connection('central')->table('marketplace_commission_rates')->insert([
            'rate_percent' => 0, 'effective_from' => '2026-01-01', 'notes' => 'Launch: commission off',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Schema::connection('central')->create('marketplace_payouts', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->string('reference', 40)->unique();
            $table->string('phone', 20)->nullable();
            $table->decimal('amount', 12, 2);
            $table->unsignedInteger('lines_count')->default(0);
            // processing | paid | failed (will retry) | needs_check (timeout) | failed_final
            $table->string('status', 20)->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable()->index();
            $table->string('conversation_id', 100)->nullable()->index();
            $table->string('originator_conversation_id', 100)->nullable();
            $table->string('mpesa_receipt', 40)->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::connection('central')->create('marketplace_payout_lines', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('type', 20); // sale | clawback | adjustment
            $table->string('description', 255);
            $table->decimal('gross', 12, 2)->default(0);
            $table->decimal('processing_fee', 12, 2)->default(0);
            $table->decimal('commission_rate', 5, 2)->default(0);
            $table->decimal('commission_amount', 12, 2)->default(0);
            $table->decimal('net', 12, 2); // what the line adds to (or takes from) the shop's payout
            $table->string('source', 20)->nullable(); // own_link | advert | marketplace
            $table->string('payment_method', 20)->nullable();
            $table->timestamp('handed_over_at')->nullable();
            $table->timestamp('eligible_at')->index();
            $table->foreignId('payout_id')->nullable()->constrained('marketplace_payouts')->nullOnDelete();
            $table->timestamps();
            $table->unique(['order_id', 'type']);
            $table->index(['tenant_id', 'payout_id', 'eligible_at']);
        });

        Schema::connection('central')->table('business_details', function (Blueprint $table) {
            $table->string('payout_phone', 20)->nullable()->after('business_phone');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('business_details', fn (Blueprint $table) => $table->dropColumn('payout_phone'));
        Schema::connection('central')->dropIfExists('marketplace_payout_lines');
        Schema::connection('central')->dropIfExists('marketplace_payouts');
        Schema::connection('central')->dropIfExists('marketplace_commission_rates');
    }
};
