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
        Schema::connection('central')->create('business_details', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->unique();
            
            // Business Information
            $table->string('business_name');
            $table->text('business_description')->nullable();
            $table->string('business_logo')->nullable();
            $table->string('business_banner')->nullable();
            
            // Business Type & Category
            $table->foreignId('business_type_id')->constrained('business_types');
            $table->foreignId('business_category_id')->constrained('business_categories');
            
            // Contact Information
            $table->string('business_email')->nullable();
            $table->string('business_phone');            
            $table->string('contact_person')->nullable();
            
            // Location Information
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('county')->nullable();            

            // Operating Hours & Delivery (JSON format)
            $table->json('operating_hours')->nullable();
            $table->json('delivery_info')->nullable();
            
            // Ratings & Verification
            $table->decimal('rating', 3, 2)->default(0.00);
            $table->integer('rating_count')->default(0);
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            
            // Settings & Additional Data
            $table->json('settings')->nullable(); // Currency, tax rate, payment methods, etc.
            $table->json('social_media')->nullable(); // Facebook, Instagram, Twitter links
            
            // Status
            $table->enum('status', ['active', 'inactive', 'suspended', 'pending'])->default('pending');
            $table->timestamp('onboarded_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes(); // For soft deletion
            
            // Foreign Keys
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            
            // Indexes
            $table->index('business_email');
            $table->index('business_phone');
            $table->index('status');
            $table->index(['business_type_id', 'business_category_id']);
            $table->index('is_verified');        
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->dropIfExists('business_details');
    }
};
