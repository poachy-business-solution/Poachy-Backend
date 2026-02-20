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
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();

            // Link to central review
            $table->unsignedBigInteger('central_review_id')->unique();

            // Product identification
            $table->unsignedBigInteger('product_id'); // Tenant's product ID
            $table->string('product_name');
            $table->string('product_sku')->nullable();

            // Customer info (anonymized)
            $table->string('customer_name');

            // Review content
            $table->decimal('rating', 2, 1);
            $table->string('title')->nullable();
            $table->text('review_text')->nullable();
            $table->json('review_images')->nullable();
            $table->boolean('is_verified_purchase')->default(false);

            // Merchant response (stored locally)
            $table->text('merchant_response')->nullable();
            $table->timestamp('merchant_responded_at')->nullable();
            $table->enum('response_sync_status', ['pending', 'synced', 'failed'])->nullable();

            // Metadata
            $table->string('status')->default('approved'); // Only approved reviews synced to tenant
            $table->timestamp('reviewed_at');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'created_at']);
            $table->index(['central_review_id']);
            $table->index(['response_sync_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
    }
};
