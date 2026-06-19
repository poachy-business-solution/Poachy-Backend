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
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('shift_name'); // "Morning Shift", "Evening Shift"
            $table->foreignId('store_id')->nullable()->constrained('stores')->onDelete('cascade');
            // NULL = applies to all stores
            
            $table->time('scheduled_start_time'); // 08:00
            $table->time('scheduled_end_time');   // 16:00
            $table->integer('duration_minutes');
            
            $table->boolean('is_active')->default(true);
            $table->json('applicable_days')->nullable(); // ["monday", "tuesday", "wednesday"]
            
            $table->timestamps();
            
            $table->index(['store_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
