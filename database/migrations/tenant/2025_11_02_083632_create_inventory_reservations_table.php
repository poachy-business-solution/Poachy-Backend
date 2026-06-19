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
        Schema::create('inventory_reservations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('inventory_id')->constrained('inventory')->onDelete('cascade');

            $table->string('reference_type', 50); // 'OnlineOrder', 'StockTransfer', etc.
            $table->unsignedBigInteger('reference_id'); // ID of the reference (e.g., order_id)

            $table->decimal('quantity_reserved', 15, 4)->comment('In base UOM')->default(0); // e.g., 2.5000 kg

            $table->timestamp('reserved_until')->nullable(); // Auto-expiry
            $table->string('status')->default('active'); // active, fulfilled, cancelled, expired

            $table->text('cancellation_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->onDelete('restrict');

            $table->timestamps();

            // Composite index for cleanup jobs (expired + active)
            $table->index(['reserved_until', 'status'], 'idx_cleanup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_reservations');
    }
};
