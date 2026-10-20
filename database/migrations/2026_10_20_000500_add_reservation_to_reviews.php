<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item 43: reviews come only from verified buyers — a completed order, or a reserve & collect
 * code redeemed at the counter. A shop review may now come from a reservation instead of an order.
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection('central')->table('product_reviews', function (Blueprint $table) {
            $table->foreignId('reservation_id')->nullable()->after('order_id')->constrained('marketplace_reservations')->nullOnDelete();
            $table->unique(['marketplace_product_id', 'customer_id', 'reservation_id'], 'customer_reservation_product_unique');
        });

        Schema::connection('central')->table('merchant_reviews', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable()->change();
            $table->foreignId('reservation_id')->nullable()->after('order_id')->unique()->constrained('marketplace_reservations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('merchant_reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reservation_id');
        });
        Schema::connection('central')->table('product_reviews', function (Blueprint $table) {
            $table->dropUnique('customer_reservation_product_unique');
            $table->dropConstrainedForeignId('reservation_id');
        });
    }
};
