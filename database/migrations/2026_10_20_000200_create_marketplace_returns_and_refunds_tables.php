<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item 38: money going back to buyers (refunds through the gateway that took it) and return
 * cases (request → shop inspection within 48 h → buyer dispute within 48 h → admin decision).
 * A payout line can now point at the refund it claws back (several per order).
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection('central')->create('marketplace_return_cases', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->unsignedBigInteger('order_id')->index();
            $table->string('tenant_id')->index();
            $table->unsignedBigInteger('customer_id')->index();
            $table->json('items'); // [{order_item_id, name, quantity, unit_amount, amount}]
            $table->string('reason_code', 30); // changed_mind | faulty | wrong_item | not_as_described | damaged
            $table->text('description')->nullable();
            $table->json('buyer_evidence')->nullable();
            $table->boolean('shop_at_fault');
            $table->decimal('requested_amount', 12, 2);
            // requested | received | rejected | disputed | escalated | refunding | refunded | closed | expired | cancelled
            $table->string('status', 20)->index();
            $table->string('shop_decision', 10)->nullable(); // accept | partial | reject
            $table->decimal('refund_amount', 12, 2)->nullable();
            $table->text('shop_reason')->nullable();
            $table->json('shop_evidence')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('inspection_due_at')->nullable()->index();
            $table->timestamp('inspected_at')->nullable();
            $table->timestamp('restocked_at')->nullable();
            $table->timestamp('dispute_until')->nullable()->index();
            $table->timestamp('disputed_at')->nullable();
            $table->text('dispute_reason')->nullable();
            $table->json('dispute_evidence')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->string('admin_decision', 20)->nullable(); // refund_full | refund_partial | no_refund
            $table->text('admin_note')->nullable();
            $table->string('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::connection('central')->create('marketplace_refunds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->index();
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->string('tenant_id')->index();
            $table->foreignId('return_case_id')->nullable()->constrained('marketplace_return_cases')->nullOnDelete();
            $table->string('kind', 20); // cancellation | return | dispute
            $table->decimal('amount', 12, 2);
            $table->string('method', 20); // mpesa_reversal | mpesa_b2c | card
            $table->string('status', 20)->index(); // processing | completed | failed
            $table->string('reason', 500)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('provider_reference', 100)->nullable();
            $table->string('conversation_id', 100)->nullable()->index();
            $table->string('failure_reason', 500)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('idempotency_key', 120)->unique();
            $table->timestamps();
        });

        Schema::connection('central')->table('marketplace_payout_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('refund_id')->nullable()->after('order_id');
            $table->dropUnique(['order_id', 'type']);
            $table->unique(['order_id', 'type', 'refund_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('marketplace_payout_lines', function (Blueprint $table) {
            $table->dropUnique(['order_id', 'type', 'refund_id']);
            $table->unique(['order_id', 'type']);
            $table->dropColumn('refund_id');
        });
        Schema::connection('central')->dropIfExists('marketplace_refunds');
        Schema::connection('central')->dropIfExists('marketplace_return_cases');
    }
};
