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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();

            // Supplier info
            $table->string('name');
            $table->string('supplier_type'); // manufacturer, distributor, wholeseller
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();

            // Financial
            $table->decimal('credit_limit', 12, 2)->default(0);
            $table->decimal('outstanding_balance', 12, 2)->default(0);
            $table->string('payment_terms')->default('cod'); // cod, net_7, net_15, net_30, net_60            

            // Tax
            $table->string('tax_id')->nullable(); // KRA PIN, VAT number
            $table->string('registration_number')->nullable();

            // Bank account details
            $table->json('bank_account_details')->nullable();
            // e.g.{"bank":"Equity","account_name":"ABC Supplies","account_number":"123456789","branch":"Nairobi"}

            // Ratings & Relationships
            $table->decimal('rating', 3, 2)->default(0); 
            $table->integer('total_orders')->default(0); 

            // Status
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index('is_active');
            $table->index('tax_id');
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
