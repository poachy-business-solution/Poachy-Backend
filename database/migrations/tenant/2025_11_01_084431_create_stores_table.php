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
        Schema::create('stores', function (Blueprint $table) {
            $table->id();

            // Basic info
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();

            // Location info
            $table->text('address');
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();

            // Operational data
            $table->boolean('is_main_store')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('opening_hours')->nullable();

            // Management & ownership
            $table->foreignId('manager_id')->nullable()->constrained('users')->onDelete('set null');

            // Auditing
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
           
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['is_active', 'is_main_store']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
