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
        Schema::create('units_of_measure', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique(); // kg, g, l, ml, pcs, box, doz, ctn
            $table->string('name'); // Kilogram, Gram, Liter, Piece, Box
            $table->string('type'); // weight, volume, count, length, area
            $table->boolean('is_base_unit')->default(false); // gram for weight, ml for volume
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('units_of_measure');
    }
};
