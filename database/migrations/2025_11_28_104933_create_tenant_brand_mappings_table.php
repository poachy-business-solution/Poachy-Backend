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
        Schema::connection('central')->create('tenant_brand_mappings', function (Blueprint $table) {
            $table->id();

            $table->string('tenant_id');
            $table->unsignedBigInteger('tenant_brand_id'); // ID in tenant's DB
            $table->string('tenant_brand_name'); // Denormalized
            $table->string('tenant_brand_slug'); // Denormalized

            $table->foreignId('marketplace_brand_id')->constrained('marketplace_brands')->onDelete('cascade');

            // Auto-mapping metadata
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->boolean('is_auto_mapped')->default(false);
            $table->boolean('is_verified')->default(false);

            $table->timestamps();

            // Foreign Keys
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->unique(['tenant_id', 'tenant_brand_id']);

            $table->index(['marketplace_brand_id']);
            $table->index(['tenant_id', 'is_verified']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->dropIfExists('tenant_brand_mappings');
    }
};
