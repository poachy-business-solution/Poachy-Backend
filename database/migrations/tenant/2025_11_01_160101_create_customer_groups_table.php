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
        Schema::create('customer_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "VIP Members", "Wholesale Buyers"
            $table->text('description')->nullable();
            $table->decimal('discount_percentage', 5, 2)->default(0); // Automatic discount for group
            $table->boolean('requires_approval')->default(false); // Needs verification to join
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_groups');
    }
};
