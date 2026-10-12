<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every action taken through the private admin API: who, what, when, before/after.
        Schema::connection('central')->create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('admin_id', 40);
            $table->string('admin_name', 120)->nullable();
            $table->string('admin_role', 20);
            $table->string('action', 80);                    // e.g. shop.suspend, billing.credit
            $table->string('tenant_id', 36)->nullable();
            $table->string('subject_type', 60)->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('request_id', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['admin_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });

        // Shops opened by Poachy staff go through the same provisioning as self sign-up.
        Schema::connection('central')->table('signup_requests', function (Blueprint $table) {
            $table->string('source', 10)->default('self')->after('status'); // self | admin
            $table->string('created_by_admin', 40)->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('signup_requests', function (Blueprint $table) {
            $table->dropColumn(['source', 'created_by_admin']);
        });
        Schema::connection('central')->dropIfExists('admin_audit_logs');
    }
};
