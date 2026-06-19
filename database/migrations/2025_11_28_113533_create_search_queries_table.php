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
        // Search behavior analysis. Identifies popular searches, zero-result queries (to improve catalog), and search-to-purchase conversion.
        Schema::connection('central')->create('search_queries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')->nullable()->constrained('marketplace_customers')->onDelete('set null');
            $table->string('session_id');

            // Search Details
            $table->string('search_query');
            $table->integer('results_count')->default(0);
            $table->boolean('has_results')->default(true);

            // Filters Applied
            $table->json('filters_applied')->nullable();
            // {category: "Electronics", price_range: "1000-5000", brand: "Samsung"}

            // Results Interaction
            $table->integer('results_clicked')->default(0);
            $table->integer('products_added_to_cart')->default(0);
            $table->boolean('converted_to_purchase')->default(false);

            // Search Refinement
            $table->foreignId('parent_search_id')->nullable()->constrained('search_queries')->onDelete('set null');
            // Link refined searches (e.g., "laptop" → "gaming laptop")

            $table->timestamp('searched_at')->useCurrent();

            // Indexes
            $table->index(['search_query', 'searched_at']);
            $table->index(['has_results', 'searched_at']);
            $table->index(['customer_id', 'searched_at']);
            $table->fullText('search_query'); // For query analysis
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->dropIfExists('search_queries');
    }
};
