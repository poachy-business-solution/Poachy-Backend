<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The buying price the owner enters when creating a product: used for profit until a
        // delivery records its own buying price, and prefilled when receiving stock.
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('cost_price', 15, 4)->nullable()->after('base_selling_price');
        });
        Schema::table('product_variants', function (Blueprint $table) {
            $table->decimal('cost_price', 15, 4)->nullable()->after('variant_price');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', fn (Blueprint $table) => $table->dropColumn('cost_price'));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('cost_price'));
    }
};
