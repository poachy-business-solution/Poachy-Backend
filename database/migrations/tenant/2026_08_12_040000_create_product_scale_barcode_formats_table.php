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
        Schema::create('product_scale_barcode_formats', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('prefix', 20);
            $table->unsignedSmallInteger('length')->default(13);
            $table->unsignedSmallInteger('product_code_start');
            $table->unsignedSmallInteger('product_code_length');
            $table->unsignedSmallInteger('value_start');
            $table->unsignedSmallInteger('value_length');
            $table->string('value_type')->default('weight');
            $table->unsignedSmallInteger('decimal_places')->default(3);
            $table->string('checksum')->nullable();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('priority')->default(0);
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'prefix', 'length'], 'idx_scale_barcode_match');
            $table->index(['store_id', 'is_active', 'priority'], 'idx_scale_barcode_store_priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_scale_barcode_formats');
    }
};
