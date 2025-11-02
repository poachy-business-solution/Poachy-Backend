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
        Schema::create('shift_assignments', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('shift_id')->constrained('shifts')->onDelete('restrict');
            $table->foreignId('store_id')->constrained('stores')->onDelete('restrict');
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            
            $table->date('shift_date');
            
            // Actual times (clock in/out)
            $table->timestamp('actual_start')->nullable();
            $table->timestamp('actual_end')->nullable();
            
            // Duration tracking            
            $table->integer('actual_duration_minutes')->nullable();
            
            // Status
            $table->string('status')->default('scheduled'); 
            // scheduled, in_progress, completed, cancelled, no_show
            
            // Cash handling
            $table->decimal('opening_cash', 15, 2)->nullable();
            $table->decimal('closing_cash', 15, 2)->nullable();
            $table->string('cash_variance_reason')->nullable();
            
            // Notes
            $table->text('notes')->nullable();
            $table->text('issues_reported')->nullable(); // Equipment problems, incidents
            
            // Approval
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            
            $table->timestamps();
            
            // Prevent overlapping shifts for same user
            $table->unique(['user_id', 'shift_date', 'shift_id'], 'unique_user_shift_per_day');
            
            $table->index(['store_id', 'shift_date']);
            $table->index(['user_id', 'shift_date']);
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_assignments');
    }
};
