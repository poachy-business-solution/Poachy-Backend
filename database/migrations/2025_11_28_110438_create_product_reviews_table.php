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
        Schema::connection('central')->create('product_reviews', function (Blueprint $table) {
            $table->id();

            $table->foreignId('marketplace_product_id')->constrained('marketplace_products')->onDelete('cascade');
            $table->foreignId('customer_id')->constrained('marketplace_customers')->onDelete('cascade');
            $table->foreignId('order_id')->nullable()->constrained('marketplace_orders')->onDelete('set null');

            // Review Content
            $table->decimal('rating', 2, 1); // 1-5 stars
            $table->string('title')->nullable();
            $table->text('review_text')->nullable();

            // Media
            $table->json('review_images')->nullable();

            // Verification
            $table->boolean('is_verified_purchase')->default(false);

            // Moderation
            $table->string('status')->default('pending'); // pending, approved, rejected, flagged
            $table->text('rejection_reason')->nullable();
            $table->timestamp('moderated_at')->nullable();
            $table->unsignedBigInteger('moderated_by')->nullable();

            // Helpful votes
            $table->integer('helpful_count')->default(0);
            $table->integer('not_helpful_count')->default(0);

            // Merchant Response
            $table->text('merchant_response')->nullable();
            $table->timestamp('merchant_responded_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['marketplace_product_id', 'customer_id', 'order_id'], 'customer_order_product_unique');

            $table->index(['marketplace_product_id', 'status']);
            $table->index(['customer_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->dropIfExists('product_reviews');
    }
};
