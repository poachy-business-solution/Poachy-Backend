<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Self sign-ups from poachy.com/signup: details, phone + email checks, provisioning progress.
        Schema::connection('central')->create('signup_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('shop_name', 120);
            $table->string('owner_name', 120);
            $table->string('phone', 20);                       // E.164 without "+"
            $table->string('email', 191);
            $table->string('town', 120);
            $table->unsignedSmallInteger('branches')->default(1);
            $table->unsignedBigInteger('business_category_id')->nullable();
            $table->string('workspace', 40)->nullable();
            $table->string('phone_code_hash', 64)->nullable();
            $table->string('email_code_hash', 64)->nullable();
            $table->timestamp('codes_expire_at')->nullable();
            $table->unsignedTinyInteger('code_attempts')->default(0);
            $table->unsignedTinyInteger('code_sends')->default(0);
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password_hash')->nullable();       // cleared once the owner exists
            $table->timestamp('terms_accepted_at')->nullable();
            $table->string('status', 16)->default('pending');  // pending | verified | provisioning | ready | failed
            $table->string('tenant_id', 36)->nullable();
            $table->string('failure_reason')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('phone');
            $table->index('email');
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('signup_requests');
    }
};
