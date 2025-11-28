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
        Schema::connection('central')->create('wishlists', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')->constrained('marketplace_customers')->onDelete('cascade');
            $table->foreignId('marketplace_product_id')->constrained('marketplace_products')->onDelete('cascade');

            $table->text('notes')->nullable();
            $table->integer('desired_quantity')->default(1);

            $table->timestamps();

            $table->unique(['customer_id', 'marketplace_product_id']);

            $table->index('customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->dropIfExists('wishlists');
    }
};
