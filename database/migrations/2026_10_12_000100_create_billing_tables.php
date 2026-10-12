<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trading-day prepaid billing (central). A shop keeps a balance, tops it up with M-Pesa, and is
 * charged each night for every branch that sold that day (capped per month).
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('central');

        // Price list with effective dates: a change is a new row, old rows stay for history.
        $schema->create('billing_prices', function (Blueprint $table) {
            $table->id();
            $table->date('effective_from')->unique();
            $table->decimal('first_branch_day', 10, 2);
            $table->decimal('extra_branch_day', 10, 2);
            $table->decimal('first_branch_cap', 10, 2);   // per calendar month
            $table->decimal('extra_branch_cap', 10, 2);   // per extra branch, per calendar month
            $table->unsignedSmallInteger('trial_days');
            $table->decimal('min_topup', 10, 2);
            $table->unsignedSmallInteger('low_balance_days'); // warn when the balance covers fewer trading days
            $table->unsignedSmallInteger('grace_days');
            $table->string('notes')->nullable();
            $table->string('created_by', 64)->nullable();
            $table->timestamps();
        });

        $schema->create('billing_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36)->unique();
            $table->decimal('balance', 12, 2)->default(0);
            $table->string('status', 10);                    // trial | active | grace | limited
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('grace_until')->nullable();
            $table->timestamp('limited_since')->nullable();
            $table->timestamp('low_balance_notified_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('status');
        });

        // Append-only statement. amount > 0 credits the balance, < 0 debits it.
        $schema->create('billing_ledger', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36);
            $table->foreignId('billing_account_id')->constrained('billing_accounts')->cascadeOnDelete();
            $table->string('type', 12);                      // topup | charge | adjustment | refund
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->string('description');
            $table->string('source', 20);                    // mpesa_c2b | mpesa_stk | admin | system
            $table->string('reference', 100)->nullable();    // M-Pesa receipt, billing day, admin note…
            $table->string('idempotency_key', 120)->nullable()->unique();
            $table->string('created_by', 64)->nullable();    // "system" or an admin id
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['type', 'created_at']);
        });

        // One row per branch per trading day — the unique key makes double billing impossible.
        $schema->create('billing_days', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36);
            $table->unsignedBigInteger('store_id');          // the branch's id in the tenant database
            $table->date('date');                            // Africa/Nairobi calendar day
            $table->decimal('amount', 10, 2);                // after the monthly cap (may be 0)
            $table->boolean('is_main_branch');
            $table->foreignId('billing_ledger_id')->nullable()->constrained('billing_ledger')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['tenant_id', 'store_id', 'date']);
            $table->index(['tenant_id', 'date']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        // poachy.com/pay/<token>. Only a hash of the token is stored.
        $schema->create('payment_links', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36);
            $table->string('token_hash', 64)->unique();
            $table->decimal('amount', 12, 2)->nullable();    // suggested amount; null = payer chooses
            $table->string('purpose', 30)->default('topup');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->string('created_by', 64)->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('central');
        $schema->dropIfExists('payment_links');
        $schema->dropIfExists('billing_days');
        $schema->dropIfExists('billing_ledger');
        $schema->dropIfExists('billing_accounts');
        $schema->dropIfExists('billing_prices');
    }
};
