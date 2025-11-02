<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sync_queue_outbound', function (Blueprint $table) {
            $table->id();
            
            // ============================================
            // WHAT TO SYNC (Polymorphic)
            // ============================================
            $table->string('syncable_type', 100); 
            // 'Product', 'ProductVariant', 'ProductBundle', 'Inventory', 'Store', 'MarketplaceOrder'
            
            $table->unsignedBigInteger('syncable_id');
            // ID of the record being synced
            
            $table->index(['syncable_type', 'syncable_id'], 'idx_syncable');
            
            // ============================================
            // SYNC ACTION TYPE
            // ============================================
            $table->string('action', 50); 
            // 'create', 'update', 'delete', 'activate', 'deactivate'
            
            // ============================================
            // PAYLOAD DATA
            // ============================================
            $table->json('payload')->nullable();
            // Complete data snapshot to send to central
            // Example: {"name": "Product X", "price": 100, "stock": 50}
            
            $table->json('changes')->nullable();
            // For 'update' actions: only changed fields
            // Example: {"price": {"old": 90, "new": 100}}
            
            // ============================================
            // PRIORITY & SCHEDULING
            // ============================================
            $table->unsignedTinyInteger('priority')->default(5);
            // 1 = Critical (order updates), 5 = Normal, 10 = Low (bulk updates)
            
            $table->timestamp('scheduled_at')->nullable();
            // When to process this sync (NULL = immediate)
            
            // ============================================
            // SYNC STATUS TRACKING
            // ============================================
            $table->string('status', 30)->default('pending');
            // 'pending', 'processing', 'completed', 'failed', 'cancelled'
            
            $table->unsignedTinyInteger('retry_count')->default(0);
            // How many times sync has been attempted
            
            $table->unsignedTinyInteger('max_retries')->default(3);
            // Maximum retry attempts before marking as failed
            
            // ============================================
            // SYNC RESULTS & ERRORS
            // ============================================
            $table->text('error_message')->nullable();
            // Error details if sync failed
            
            $table->string('error_code', 50)->nullable();
            // Standardized error codes (e.g., 'NETWORK_ERROR', 'VALIDATION_FAILED')
            
            $table->json('sync_response')->nullable();
            // Response from central database (success confirmation, IDs mapping, etc.)
            
            $table->string('central_record_id')->nullable();
            // ID of the record in central database after successful sync
            // Used for mapping: tenant product_id → central marketplace_product_id
            
            // ============================================
            // TIMESTAMPS & AUDIT
            // ============================================
            $table->timestamp('processing_started_at')->nullable();
            // When sync processing began
            
            $table->timestamp('completed_at')->nullable();
            // When sync successfully completed
            
            $table->timestamp('failed_at')->nullable();
            // When sync permanently failed (after max retries)
            
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            // User who triggered the sync (if manual)
            
            $table->timestamps(); // created_at, updated_at
            
            // ============================================
            // BATCH PROCESSING
            // ============================================
            $table->string('batch_id', 100)->nullable();
            // Group related syncs together (e.g., bulk product upload)
            
            $table->unsignedInteger('batch_sequence')->nullable();
            // Order within batch (process in sequence)
            
            // ============================================
            // DEDUPLICATION
            // ============================================
            $table->string('idempotency_key', 100)->unique()->nullable();
            // Prevent duplicate syncs: hash of (syncable_type + syncable_id + action + payload_hash)
            
            // ============================================
            // INDEXES FOR PERFORMANCE
            // ============================================
            
            // Primary processing queue index
            $table->index(['status', 'priority', 'scheduled_at'], 'idx_queue_processing');
            
            // Retry management
            $table->index(['status', 'retry_count', 'created_at'], 'idx_retry_queue');
            
            // Lookup by record
            $table->index(['syncable_type', 'syncable_id', 'status'], 'idx_record_sync_status');
            
            // Batch processing
            $table->index(['batch_id', 'batch_sequence'], 'idx_batch_processing');
            
            // Failed syncs investigation
            $table->index(['status', 'failed_at'], 'idx_failed_syncs');
            
            // Cleanup old records
            $table->index(['status', 'completed_at'], 'idx_cleanup');
        });
        
        // ============================================
        // TABLE COMMENT
        // ============================================
        DB::statement("
            ALTER TABLE sync_queue_outbound 
            COMMENT = 'Queue for syncing tenant data to central marketplace database. 
                       Processed by background workers. Retains 30-day history.'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_queue_outbound');
    }
};
