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
        Schema::create('uom_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_uom_id')->constrained('units_of_measure')->onDelete('cascade');
            $table->foreignId('to_uom_id')->constrained('units_of_measure')->onDelete('cascade');
            $table->decimal('conversion_factor', 15, 6); // e.g., 1 kg = 1000 g (factor: 1000)
            $table->timestamps();
            
            $table->unique(['from_uom_id', 'to_uom_id']);
            $table->index(['from_uom_id', 'to_uom_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('uom_conversions');
    }
};
