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
        Schema::create('shift_swap_requests', function (Blueprint $table) {
            $table->id();

            // Assignments being swapped
            $table->foreignId('requester_assignment_id')
                ->constrained('shift_assignments')
                ->onDelete('restrict');
            $table->foreignId('target_assignment_id')
                ->constrained('shift_assignments')
                ->onDelete('restrict');

            // Users involved
            $table->foreignId('requester_id')
                ->constrained('users')
                ->onDelete('restrict');
            $table->foreignId('target_user_id')
                ->constrained('users')
                ->onDelete('restrict');

            // Request details
            $table->text('reason');

            // Manager action
            $table->foreignId('manager_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');
            $table->text('manager_note')->nullable();
            $table->timestamp('swapped_at')->nullable(); // When the swap was executed

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['requester_id', 'swapped_at']);
            $table->index(['target_user_id', 'swapped_at']);
            $table->index('swapped_at');
            $table->index(['requester_assignment_id']);
            $table->index(['target_assignment_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_swap_requests');
    }
};
