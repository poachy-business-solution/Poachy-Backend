<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketplace items: the same product sold by several shops (matched by barcode first,
 * mapped by admin second) so one product page lists every shop's offer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('marketplace_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('barcode', 50)->nullable()->unique();
            $table->foreignId('marketplace_category_id')->nullable()->constrained('marketplace_categories')->nullOnDelete();
            $table->foreignId('marketplace_brand_id')->nullable()->constrained('marketplace_brands')->nullOnDelete();
            $table->string('primary_image')->nullable();
            $table->text('description')->nullable();
            $table->string('created_by_admin')->nullable(); // admin id when mapped by hand
            $table->timestamps();
        });

        Schema::connection('central')->table('marketplace_products', function (Blueprint $table) {
            $table->string('barcode', 50)->nullable()->index()->after('sku');
            $table->foreignId('marketplace_item_id')->nullable()->after('barcode')->constrained('marketplace_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('marketplace_products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('marketplace_item_id');
            $table->dropColumn('barcode');
        });
        Schema::connection('central')->dropIfExists('marketplace_items');
    }
};
