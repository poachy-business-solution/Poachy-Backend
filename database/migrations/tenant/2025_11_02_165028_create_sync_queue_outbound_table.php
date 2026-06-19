<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_queue_outbound', function (Blueprint $table) {
            $table->id();

            // ============================================
            // TENANT CONTEXT (CRITICAL FOR MULTI-TENANCY)
            // ============================================
            $table->string('tenant_id', 100)->index();
            // Ensures queue isolation per tenant

            // ============================================
            // WHAT TO SYNC (Polymorphic + UUID)
            // ============================================
            $table->string('syncable_type', 100);
            // 'Product', 'ProductVariant', 'ProductBundle', 'Inventory', 'Store'

            $table->unsignedBigInteger('syncable_id');
            // Local tenant database ID

            $table->uuid('tenant_record_uuid')->nullable();
            // UUID of the record in tenant DB (for conflict-free mapping)

            $table->index(['syncable_type', 'syncable_id'], 'idx_syncable');
            $table->index(['tenant_id', 'syncable_type', 'syncable_id'], 'idx_tenant_syncable');

            // ============================================
            // SYNC ACTION TYPE
            // ============================================
            $table->enum('action', [
                'create',
                'update',
                'delete',
                'activate',
                'deactivate',
                'bulk_update'
            ])->default('create');

            // ============================================
            // PAYLOAD DATA
            // ============================================
            $table->json('payload')->nullable();
            // Complete data snapshot to send to central

            $table->json('changes')->nullable();
            // For 'update' actions: only changed fields

            $table->json('metadata')->nullable();
            // Additional context: user_id, ip_address, source, etc.

            // ============================================
            // PRIORITY & SCHEDULING
            // ============================================
            $table->unsignedTinyInteger('priority')->default(5);
            // 1 = Critical, 3 = High, 5 = Normal, 8 = Low, 10 = Bulk

            $table->timestamp('scheduled_at')->nullable();
            // When to process (NULL = immediate)

            $table->timestamp('expires_at')->nullable();
            // Don't process if past this time (stale data)

            // ============================================
            // SYNC STATUS TRACKING
            // ============================================
            $table->enum('status', [
                'pending',
                'queued',
                'processing',
                'validating',
                'syncing',
                'completed',
                'failed',
                'cancelled',
                'stale',
                'duplicate'
            ])->default('pending');

            // ============================================
            // CONCURRENCY CONTROL (LOCKING)
            // ============================================
            $table->string('lock_token', 100)->nullable();
            // Unique token held by processing worker

            $table->timestamp('locked_at')->nullable();
            // When the lock was acquired

            $table->unsignedInteger('locked_by_worker_id')->nullable();
            // Which Horizon worker has the lock

            $table->index(['status', 'locked_at'], 'idx_available_for_processing');

            // ============================================
            // RETRY MECHANISM
            // ============================================
            $table->unsignedTinyInteger('retry_count')->default(0);

            $table->unsignedTinyInteger('max_retries')->default(3);

            $table->timestamp('next_retry_at')->nullable();
            // Calculated based on backoff strategy

            $table->string('backoff_strategy', 30)->default('exponential');
            // 'exponential', 'linear', 'fixed'

            // ============================================
            // SYNC RESULTS & ERRORS
            // ============================================
            $table->text('error_message')->nullable();

            $table->string('error_code', 50)->nullable();
            // 'NETWORK_ERROR', 'VALIDATION_FAILED', 'DUPLICATE_ENTRY', etc.

            $table->json('error_details')->nullable();
            // Stack trace, validation errors, etc.

            $table->json('sync_response')->nullable();
            // Response from central database

            // ============================================
            // CENTRAL DATABASE MAPPING
            // ============================================
            $table->string('central_record_id')->nullable();
            $table->uuid('central_record_uuid')->nullable();
            $table->string('central_table', 100)->nullable();

            // ============================================
            // TIMESTAMPS & AUDIT
            // ============================================
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps(); // created_at, updated_at

            // ============================================
            // BATCH PROCESSING
            // ============================================
            $table->uuid('batch_id')->nullable();
            // Group related syncs together

            $table->unsignedInteger('batch_sequence')->nullable();
            // Order within batch

            $table->unsignedInteger('batch_total')->nullable();
            // Total items in batch (for progress tracking)

            $table->index(['batch_id', 'batch_sequence'], 'idx_batch_processing');

            // ============================================
            // DEDUPLICATION
            // ============================================
            $table->string('idempotency_key', 100)->unique()->nullable();
            // Format: md5(tenant_id . syncable_type . syncable_id . action . payload_hash)

            $table->string('payload_hash', 64)->nullable();
            // SHA256 of payload for change detection

            // ============================================
            // PERFORMANCE INDEXES
            // ============================================

            // Main queue processing
            $table->index(
                ['tenant_id', 'status', 'priority', 'scheduled_at'],
                'idx_queue_main'
            );

            // Retry queue
            $table->index(
                ['status', 'retry_count', 'next_retry_at'],
                'idx_retry_queue'
            );

            // Status tracking per tenant
            $table->index(
                ['tenant_id', 'syncable_type', 'status'],
                'idx_tenant_status'
            );

            // Failed syncs investigation
            $table->index(['status', 'failed_at'], 'idx_failed_syncs');

            // Stale job cleanup
            $table->index(['expires_at', 'status'], 'idx_stale_cleanup');

            // Cleanup completed records (older than 30 days)
            $table->index(
                ['status', 'completed_at'],
                'idx_cleanup_completed'
            );

            // Find pending syncs for specific record
            $table->index(
                ['tenant_id', 'syncable_type', 'syncable_id', 'status'],
                'idx_record_pending_syncs'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_queue_outbound');
    }
};
