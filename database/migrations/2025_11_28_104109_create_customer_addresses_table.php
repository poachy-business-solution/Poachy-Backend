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
        Schema::connection('central')->create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('marketplace_customers')->onDelete('cascade');

            // Address Details
            $table->string('address_type')->default('home'); // home, work, other
            $table->string('label')->nullable(); // "Home", "Office", "Mom's Place"
            $table->string('recipient_name');
            $table->string('recipient_phone');

            // Location
            $table->text('address_line');
            $table->string('building_apartment')->nullable();
            $table->string('city');
            $table->string('county');
            $table->string('postal_code')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            // Delivery Instructions
            $table->text('delivery_instructions')->nullable();

            // Flags
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['customer_id', 'is_default']);
            $table->index(['city', 'county']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->dropIfExists('customer_addresses');
    }
};
