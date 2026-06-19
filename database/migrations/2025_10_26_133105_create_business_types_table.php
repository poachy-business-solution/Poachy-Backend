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
        Schema::connection('central')->create('business_types', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "Retail & Consumer Goods", "Food & Beverage"
            $table->string('slug')->unique(); // e.g., "retail-consumer-goods"
            $table->text('description')->nullable();            
            $table->boolean('is_active')->default(true);            
            $table->timestamps();
            
            $table->index('slug');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->dropIfExists('business_types');
    }
};
