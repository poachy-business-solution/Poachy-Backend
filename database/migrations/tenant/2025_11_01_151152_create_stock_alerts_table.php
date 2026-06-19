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
        Schema::create('stock_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');

            // Alert details
            $table->string('alert_type'); // low_stock, out_of_stock, expiring_soon
            $table->decimal('current_quantity', 15, 4)->comment('In base UOM'); // In base UOM
            $table->decimal('threshold_quantity', 15, 4)->nullable(); // The trigger level

            // Resolution details
            $table->boolean('is_resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->json('notified_users')->nullable(); // Array of user IDs notified
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            $table->index(['store_id', 'product_id', 'is_resolved']);
            $table->index(['alert_type', 'is_resolved']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_alerts');
    }
};
