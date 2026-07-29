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
        Schema::connection('central')->table('business_subscriptions', function (Blueprint $table) {
            $table->boolean('reminder_7day_sent')->default(false);
            $table->timestamp('reminder_7day_sent_at')->nullable();
            $table->boolean('reminder_1day_sent')->default(false);
            $table->timestamp('reminder_1day_sent_at')->nullable();
            $table->boolean('expired_notified')->default(false);
            $table->timestamp('expired_notified_at')->nullable();
            $table->boolean('activation_notified')->default(false);
            $table->timestamp('activation_notified_at')->nullable();

            $table->index(['status', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->table('business_subscriptions', function (Blueprint $table) {
            $table->dropIndex(['status', 'end_date']);

            $table->dropColumn([
                'reminder_7day_sent',
                'reminder_7day_sent_at',
                'reminder_1day_sent',
                'reminder_1day_sent_at',
                'expired_notified',
                'expired_notified_at',
                'activation_notified',
                'activation_notified_at',
            ]);
        });
    }
};
