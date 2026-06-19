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
        Schema::create('expiry_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('product_batches')->onDelete('cascade');
            $table->string('alert_level'); // warning, urgent, expired
            $table->date('alert_date'); // When alert was generated
            $table->integer('days_until_expiry')->nullable(); // For quick reference
            $table->boolean('is_resolved')->default(false);
            $table->string('resolution_action')->nullable(); // sold, discounted, disposed, returned, other
            $table->text('notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'is_resolved']);
            $table->index(['alert_level', 'is_resolved']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expiry_alerts');
    }
};
