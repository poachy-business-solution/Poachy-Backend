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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            
            // Who did it
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('user_name')->nullable(); // Denormalized for safety
            $table->string('ip_address', 45)->nullable();
            
            // What was done
            $table->string('action', 50); // created, updated, deleted, approved, rejected
            $table->string('model_type', 100); // Product, Sale, PurchaseOrder
            $table->unsignedBigInteger('model_id');
            
            // Changes
            $table->json('old_values')->nullable(); // Snapshot before
            $table->json('new_values')->nullable(); // Snapshot after
            
            // Context
            $table->text('description')->nullable(); // Human-readable description
            $table->string('tags')->nullable(); // "price_change,critical"
            
            $table->timestamp('created_at')->useCurrent();
            
            // Indexes
            $table->index(['model_type', 'model_id']);
            $table->index(['user_id', 'created_at']);
            $table->index(['action']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
