<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Which branches sold on which (Nairobi) days, as reported by each shop. Billing reads
        // this table — central never reads tenant databases.
        Schema::connection('central')->create('tenant_sale_days', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36);
            $table->unsignedBigInteger('store_id');
            $table->date('date');
            $table->unsignedInteger('sales_count');
            $table->boolean('is_main_branch');
            $table->timestamp('reported_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'store_id', 'date']);
            $table->index(['date', 'tenant_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('tenant_sale_days');
    }
};
