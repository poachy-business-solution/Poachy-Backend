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
        Schema::create('inventory_waste', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('batch_id')->nullable()->constrained('product_batches')->onDelete('set null');

            $table->string('waste_type'); // expired, damaged, stolen, lost, quality_issue, other
            $table->decimal('quantity_wasted', 15, 4); // In base UOM
            $table->decimal('cost_per_base_uom', 15, 2);
            $table->decimal('total_loss', 15, 2); // Financial impact
            $table->date('waste_date');
            $table->text('reason')->nullable();
            $table->string('approval_status')->default('pending'); // pending, approved, rejected
            $table->foreignId('reported_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('restrict');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            
            $table->index(['store_id', 'waste_date']);
            $table->index(['approval_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_waste');
    }
};
