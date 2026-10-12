<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per verification check a shop submitted (staff upload them). Files live on the
        // private `verifications` disk; only their path is stored here.
        Schema::connection('central')->create('tenant_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36);
            $table->string('check', 12);                    // id | kra_pin | permit | location | photo
            $table->string('file_path')->nullable();
            $table->string('file_mime', 60)->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->string('value', 120)->nullable();       // KRA PIN number, "lat,lng"
            $table->string('status', 10)->default('pending'); // pending | verified | rejected | replaced
            $table->string('uploaded_by', 64)->nullable();
            $table->string('reviewed_by', 64)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'check', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('tenant_verifications');
    }
};
