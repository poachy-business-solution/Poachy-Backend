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
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number')->unique(); // e.g., "TRF-2025-001"
            $table->foreignId('from_store_id')->constrained('stores')->onDelete('restrict');
            $table->foreignId('to_store_id')->constrained('stores')->onDelete('restrict');

            $table->string('status')->default('pending'); // pending, approved, in_transit, completed, cancelled
            $table->date('transfer_date');
            $table->date('expected_arrival_date')->nullable();
            $table->date('actual_arrival_date')->nullable();

            $table->foreignId('requested_by')->constrained('users')->onDelete('restrict');            
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('restrict');
            $table->timestamp('approved_at')->nullable();

            $table->foreignId('sent_by')->nullable()->constrained('users')->onDelete('restrict');
            $table->timestamp('sent_at')->nullable();

            $table->foreignId('received_by')->nullable()->constrained('users')->onDelete('restrict');                     
            $table->timestamp('received_at')->nullable();
            
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable(); // If cancelled
            $table->timestamps();
            
            $table->index(['from_store_id', 'status']);
            $table->index(['to_store_id', 'status']);
            $table->index('transfer_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_transfers');
    }
};
