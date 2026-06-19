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
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();

            $table->string('tax_name', 50); // e.g., 'VAT', 'Import Duty'
            $table->decimal('rate', 5, 2);   // e.g., 16.00, 5.00
            $table->date('effective_from');
            $table->date('effective_until')->nullable(); // NULL = ongoing
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);

            $table->timestamp('created_at')->useCurrent();

            // Indexes for performance
            $table->index(['effective_from', 'effective_until']);
            $table->index('is_active');

            // Ensure only one active rate per tax_name at a time
            $table->unique(
                ['tax_name', 'effective_from'],
                'unique_active_tax_rate'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
    }
};
