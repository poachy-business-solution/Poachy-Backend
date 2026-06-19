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
        Schema::connection('central')->create('marketplace_customers', function (Blueprint $table) {
            $table->id();

            // Basic Info
            $table->string('customer_number')->unique(); // e.g., "MKT-CUST-000001"
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->unique();
            $table->string('password');

            // Profile
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable(); // male, female, other, prefer_not_to_say
            $table->string('profile_picture')->nullable();

            // Account Status
            $table->boolean('is_active')->default(true);
            $table->boolean('email_verified')->default(false);
            $table->boolean('phone_verified')->default(false);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();

            // Marketing Preferences
            $table->boolean('accepts_marketing')->default(true);
            $table->boolean('accepts_sms')->default(true);

            // Security
            $table->rememberToken();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('email');
            $table->index('phone');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->dropIfExists('marketplace_customers');
    }
};
