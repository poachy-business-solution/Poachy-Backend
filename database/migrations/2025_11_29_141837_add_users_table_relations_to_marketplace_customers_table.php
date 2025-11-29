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
        Schema::connection('central')->table('users', function (Blueprint $table) {
            $table->string('user_type')->default('customer')->after('id'); // customer, admin, vendor, etc.
            $table->index('user_type');
        });

        Schema::connection('central')->table('marketplace_customers', function (Blueprint $table) {
            $table->foreignId('user_id')->after('id')->constrained('users')->onDelete('cascade');
            $table->unique('user_id'); // One customer per user
        });

        Schema::connection('central')->table('marketplace_customers', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->dropColumn([
                'name',
                'email',
                'password',
                'email_verified',
                'email_verified_at',
                'remember_token',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore authentication fields to marketplace_customers
        Schema::connection('central')->table('marketplace_customers', function (Blueprint $table) {
            $table->string('name')->after('customer_number');
            $table->string('email')->unique()->after('name');
            $table->string('password')->after('phone');
            $table->boolean('email_verified')->default(false)->after('profile_picture');
            $table->timestamp('email_verified_at')->nullable()->after('email_verified');
            $table->rememberToken()->after('accepts_sms');
        });

        // Remove user relationship
        Schema::connection('central')->table('marketplace_customers', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id']);
            $table->dropColumn('user_id');
        });

        // Remove user_type from users
        Schema::connection('central')->table('users', function (Blueprint $table) {
            $table->dropColumn('user_type');
        });
    }
};
