<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every email and SMS Poachy sends (or fails to send), for support and the admin send log.
        Schema::connection('central')->create('message_logs', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 10);                 // email | sms
            $table->string('template', 120);               // mailable class short name or SMS template key
            $table->string('recipient', 191);              // email address or E.164 phone
            $table->string('subject', 255)->nullable();    // email subject / first line of the SMS
            $table->string('tenant_id', 36)->nullable();
            $table->string('status', 10);                  // queued | sent | failed
            $table->string('provider_id', 100)->nullable();
            $table->text('error')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['channel', 'created_at']);
            $table->index('recipient');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('message_logs');
    }
};
