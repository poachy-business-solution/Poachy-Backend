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
        Schema::create('product_barcode_suggestions', function (Blueprint $table) {
            $table->id();
            $table->morphs('suggested_barcodeable', 'idx_barcode_suggestion_target');
            $table->string('barcode', 50);
            $table->string('barcode_type')->default('INTERNAL');
            $table->string('status')->default('pending');
            $table->boolean('is_primary')->default(false);
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('region', 10)->nullable();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('approved_barcode_id')->nullable()->constrained('product_barcodes')->nullOnDelete();
            $table->timestamps();

            $table->index(['barcode', 'status', 'store_id'], 'idx_barcode_suggestion_lookup');
            $table->index(['status', 'created_at'], 'idx_barcode_suggestion_review_queue');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_barcode_suggestions');
    }
};
