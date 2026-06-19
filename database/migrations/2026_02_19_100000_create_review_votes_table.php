<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('review_votes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')->constrained('marketplace_customers')->onDelete('cascade');

            // Polymorphic relation — voteable_type = 'App\Models\ProductReview' | 'App\Models\MerchantReview'
            $table->string('voteable_type');
            $table->unsignedBigInteger('voteable_id');

            $table->string('vote_type'); // 'helpful' | 'not_helpful'

            $table->timestamps();

            $table->unique(['customer_id', 'voteable_type', 'voteable_id'], 'customer_voteable_unique');
            $table->index(['voteable_type', 'voteable_id'], 'voteable_index');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('review_votes');
    }
};
