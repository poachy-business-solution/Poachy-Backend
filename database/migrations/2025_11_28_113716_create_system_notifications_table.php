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
        Schema::connection('central')->create('system_notifications', function (Blueprint $table) {
            $table->id();

            // Recipient
            $table->string('recipient_type'); // marketplace_customer, tenant
            $table->unsignedBigInteger('recipient_id');

            // Notification Details
            $table->string('type');
            // order_update, payment_received, product_review, low_stock, 
            // subscription_expiring, abandoned_cart, price_drop, back_in_stock

            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable();

            // Action
            $table->string('action_url')->nullable();
            $table->string('action_label')->nullable();

            // Status
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();

            // Channels
            $table->boolean('sent_via_email')->default(false);
            $table->timestamp('email_sent_at')->nullable();
            $table->boolean('sent_via_sms')->default(false);
            $table->timestamp('sms_sent_at')->nullable();
            $table->boolean('sent_via_push')->default(false);
            $table->timestamp('push_sent_at')->nullable();

            $table->timestamps();

            $table->index(['recipient_type', 'recipient_id', 'is_read']);
            $table->index(['type', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->dropIfExists('system_notifications');
    }
};
