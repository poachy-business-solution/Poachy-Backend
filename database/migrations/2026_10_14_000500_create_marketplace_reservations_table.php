<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reserve & collect: a buyer holds an item at a shop for 24 h with a PCH- code, pays at the
 * counter, and the cashier redeems the code on the POS so the sale is linked to the reservation.
 * The stock hold itself lives in the shop's own inventory reservations (synced like an order).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('marketplace_reservations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique(); // PCH-XXXX
            $table->string('tenant_id')->index();
            $table->foreignId('customer_id')->constrained('marketplace_customers')->restrictOnDelete();
            $table->foreignId('marketplace_product_id')->constrained('marketplace_products')->restrictOnDelete();
            $table->unsignedBigInteger('tenant_product_id')->nullable();
            $table->unsignedBigInteger('tenant_variant_id')->nullable();
            $table->unsignedBigInteger('tenant_bundle_id')->nullable();
            $table->string('product_name');
            $table->string('product_slug')->nullable();
            $table->string('product_image')->nullable();
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_price', 15, 2);
            $table->string('uom_code', 20)->nullable();
            $table->string('status', 20)->index(); // pending, active, failed, redeemed, expired, cancelled
            $table->string('ref', 20)->nullable(); // link source: wa, ig, qr, poster… (null = marketplace)
            $table->string('buyer_name')->nullable();
            $table->string('buyer_phone', 20)->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('held_at')->nullable();
            $table->string('failed_reason', 500)->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('redeemed_at')->nullable();
            $table->unsignedBigInteger('sale_id')->nullable(); // the shop's own sale (tenant DB)
            $table->string('sale_number')->nullable();
            $table->unsignedBigInteger('redeemed_by')->nullable(); // tenant user id
            $table->string('idempotency_key', 100);
            $table->timestamps();

            $table->unique(['customer_id', 'idempotency_key']);
            $table->index(['tenant_id', 'status', 'expires_at']);
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('marketplace_reservations');
    }
};
