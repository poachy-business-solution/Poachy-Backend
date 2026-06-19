<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('review_flags', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')->constrained('marketplace_customers')->onDelete('cascade');

            // Polymorphic relation — flaggable_type = 'App\Models\ProductReview' | 'App\Models\MerchantReview'
            $table->string('flaggable_type');
            $table->unsignedBigInteger('flaggable_id');

            $table->text('reason');

            $table->timestamps();

            // One flag per customer per review
            $table->unique(['customer_id', 'flaggable_type', 'flaggable_id'], 'customer_flaggable_unique');
            $table->index(['flaggable_type', 'flaggable_id'], 'flaggable_index');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('review_flags');
    }
};
