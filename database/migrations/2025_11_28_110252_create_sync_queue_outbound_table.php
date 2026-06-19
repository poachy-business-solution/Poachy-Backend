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
        // Sends orders, payment confirmations, reviews FROM central marketplace TO tenant databases for fulfillment.
        Schema::connection('central')->create('sync_queue_outbound', function (Blueprint $table) {
            $table->id();

            // ============================================
            // DESTINATION TENANT
            // ============================================
            $table->string('tenant_id', 100);

            // ============================================
            // SYNC ENTITY
            // ============================================
            $table->string('syncable_type', 100);
            // 'MarketplaceOrder', 'MarketplaceOrderPayment', 'ProductReview', 'MerchantReview'

            $table->unsignedBigInteger('syncable_id'); // ID in central DB

            // ============================================
            // SYNC ACTION
            // ============================================
            $table->enum('action', [
                'create',       // New order
                'update',       // Order status change
                'payment_confirmed',
                'delivery_update',
                'review_posted',
                'cancel',
                'reserve_inventory',
                'release_reservation'
            ])->default('create');

            // ============================================
            // PAYLOAD
            // ============================================
            $table->json('payload'); // Complete order/payment/review data
            $table->json('metadata')->nullable();

            // ============================================
            // PRIORITY
            // ============================================
            $table->unsignedTinyInteger('priority')->default(5);
            // 1 = Critical (payment confirmed), 3 = High (new order), 5 = Normal

            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            // ============================================
            // PROCESSING STATUS
            // ============================================
            $table->enum('status', [
                'pending',
                'queued',
                'processing',
                'delivered',    // Successfully sent to tenant
                'acknowledged', // Tenant confirmed receipt
                'completed',
                'failed',
                'cancelled',
                'stale'
            ])->default('pending');

            // ============================================
            // LOCKING
            // ============================================
            $table->string('lock_token', 100)->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->unsignedInteger('locked_by_worker_id')->nullable();

            // ============================================
            // RETRY
            // ============================================
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->unsignedTinyInteger('max_retries')->default(5); // Higher for critical data
            $table->timestamp('next_retry_at')->nullable();
            $table->string('backoff_strategy', 30)->default('exponential');

            // ============================================
            // RESULTS
            // ============================================
            $table->text('error_message')->nullable();
            $table->string('error_code', 50)->nullable();
            $table->json('error_details')->nullable();
            $table->json('tenant_response')->nullable(); // Tenant's acknowledgment

            // ============================================
            // TENANT DATABASE RESULT
            // ============================================
            $table->unsignedBigInteger('tenant_record_id')->nullable(); // Created record ID in tenant DB
            $table->string('tenant_table', 100)->nullable(); // 'sales', 'customers'

            // ============================================
            // TIMESTAMPS
            // ============================================
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('delivered_at')->nullable(); // Sent to tenant
            $table->timestamp('acknowledged_at')->nullable(); // Tenant confirmed
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            // ============================================
            // BATCH
            // ============================================
            $table->uuid('batch_id')->nullable();
            $table->unsignedInteger('batch_sequence')->nullable();

            // ============================================
            // DEDUPLICATION
            // ============================================
            $table->string('idempotency_key', 100)->unique()->nullable();

            // ============================================
            // FOREIGN KEYS
            // ============================================
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            // ============================================
            // INDEXES
            // ============================================
            $table->index(['tenant_id', 'status', 'priority', 'scheduled_at'], 'idx_queue_processing');
            $table->index(['status', 'retry_count', 'next_retry_at'], 'idx_retry_queue');
            $table->index(['syncable_type', 'syncable_id'], 'idx_central_entity');
            $table->index(['status', 'failed_at'], 'idx_failed_syncs');
            $table->index(['batch_id', 'batch_sequence'], 'idx_batch');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->dropIfExists('sync_queue_outbound');
    }
};
