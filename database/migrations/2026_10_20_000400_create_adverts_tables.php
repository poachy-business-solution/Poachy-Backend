<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Item 42: paid placements on the marketplace. Types and prices are editable by Poachy; a shop
 * buys an advert on its web business account (Poachy balance or M-Pesa), never in the app.
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection('central')->create('advert_types', function (Blueprint $table) {
            $table->id();
            $table->string('key', 30)->unique(); // product_boost | featured_shop | near_you | home_spot
            $table->string('name');
            $table->string('description', 255);
            $table->decimal('price', 10, 2);
            $table->unsignedSmallInteger('days');
            $table->boolean('needs_product');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::connection('central')->table('advert_types')->insert([
            ['key' => 'product_boost', 'name' => 'Product boost', 'description' => 'A product shows first (Sponsored) in its category and in searches.', 'price' => 100, 'days' => 7, 'needs_product' => true, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'featured_shop', 'name' => 'Featured shop', 'description' => 'Your shop leads the featured shops on the marketplace home and in shop searches.', 'price' => 500, 'days' => 7, 'needs_product' => false, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'near_you', 'name' => 'Near you', 'description' => 'Your products show first to buyers looking in your county.', 'price' => 1000, 'days' => 30, 'needs_product' => false, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'home_spot', 'name' => 'Home spot', 'description' => 'A product sits in the Sponsored row on the marketplace home.', 'price' => 2000, 'days' => 7, 'needs_product' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        Schema::connection('central')->create('adverts', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->string('type_key', 30)->index();
            $table->unsignedBigInteger('marketplace_product_id')->nullable()->index();
            $table->string('county', 100)->nullable(); // near_you: the shop's county at purchase
            $table->decimal('price', 10, 2);
            $table->unsignedSmallInteger('days');
            // pending_payment | active | ended | stopped | payment_failed
            $table->string('status', 20)->index();
            $table->string('paid_with', 10); // balance | mpesa
            $table->unsignedBigInteger('billing_topup_id')->nullable()->index();
            $table->unsignedBigInteger('billing_ledger_id')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable()->index();
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->string('stopped_reason', 500)->nullable();
            $table->string('created_by')->nullable();
            $table->string('idempotency_key', 120)->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('adverts');
        Schema::connection('central')->dropIfExists('advert_types');
    }
};
