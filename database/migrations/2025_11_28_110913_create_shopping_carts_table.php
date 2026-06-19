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
        // Track shopping carts for both logged-in and guest users. Identify abandoned carts for recovery campaigns.
        Schema::connection('central')->create('shopping_carts', function (Blueprint $table) {
            $table->id();

            // Owner
            $table->foreignId('customer_id')->nullable()->constrained('marketplace_customers')->onDelete('cascade');
            // NULL = anonymous/guest cart

            // Session
            $table->string('session_id')->unique(); // For guest tracking
            $table->string('tenant_id'); // Which merchant's products

            // Status
            $table->string('status')->default('active'); // active, abandoned, converted, expired

            // Timing
            $table->timestamp('created_at');
            $table->timestamp('updated_at'); // Last activity
            $table->timestamp('abandoned_at')->nullable(); // When it became abandoned (no activity for X minutes)
            $table->timestamp('converted_at')->nullable(); // When order was placed
            $table->foreignId('converted_order_id')->nullable()->constrained('marketplace_orders')->onDelete('set null');

            // Metadata
            $table->string('device_type')->nullable(); // mobile, tablet, desktop
            $table->string('browser')->nullable();
            $table->string('platform')->nullable(); // ios, android, web
            $table->string('user_agent')->nullable();
            $table->string('ip_address')->nullable();

            // Recovery
            $table->boolean('recovery_email_sent')->default(false);
            $table->timestamp('recovery_email_sent_at')->nullable();
            $table->boolean('recovery_sms_sent')->default(false);
            $table->timestamp('recovery_sms_sent_at')->nullable();

            // Foreign Keys
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            // Indexes
            $table->index(['customer_id', 'status']);
            $table->index(['tenant_id', 'status']);
            $table->index(['status', 'abandoned_at']); // For abandoned cart campaigns
            $table->index('session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->dropIfExists('shopping_carts');
    }
};
