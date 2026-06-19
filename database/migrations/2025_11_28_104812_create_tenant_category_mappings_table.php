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
        // Maps tenant-specific categories to standardized marketplace categories.
        // Allows merchants to use their own categorization while maintaining marketplace consistency.
        Schema::connection('central')->create('tenant_category_mappings', function (Blueprint $table) {
            $table->id();

            $table->string('tenant_id');
            $table->unsignedBigInteger('tenant_category_id'); // ID in tenant's DB
            $table->string('tenant_category_name'); // Denormalized for reference
            $table->string('tenant_category_slug'); // Denormalized

            $table->foreignId('marketplace_category_id')->constrained('marketplace_categories')->onDelete('cascade');

            // Auto-mapping metadata
            $table->decimal('confidence_score', 5, 2)->nullable(); // 0-100 for auto-mapped categories
            $table->boolean('is_auto_mapped')->default(false);
            $table->boolean('is_verified')->default(false); // Merchant confirmed the mapping

            $table->timestamps();

            // Foreign Keys
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            // One tenant category maps to one marketplace category
            $table->unique(['tenant_id', 'tenant_category_id']);

            $table->index(['marketplace_category_id']);
            $table->index(['tenant_id', 'is_verified']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->dropIfExists('tenant_category_mappings');
    }
};
