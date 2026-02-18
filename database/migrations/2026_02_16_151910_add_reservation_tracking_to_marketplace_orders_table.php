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
        Schema::connection('central')->table('marketplace_orders', function (Blueprint $table) {
            $table->string('reservation_status')->default('pending')->after('order_status');
            $table->timestamp('reservation_expires_at')->nullable()->after('reservation_status');
            $table->timestamp('reservation_confirmed_at')->nullable()->after('reservation_expires_at');
            $table->text('reservation_failed_reason')->nullable()->after('reservation_confirmed_at');
            $table->timestamp('payment_deadline_at')->nullable()->after('reservation_failed_reason');

            $table->index(['reservation_status', 'reservation_expires_at'], 'idx_reservation_status_expires');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('central')->table('marketplace_orders', function (Blueprint $table) {
            $table->dropIndex('idx_reservation_status_expires');
            $table->dropColumn([
                'reservation_status',
                'reservation_expires_at',
                'reservation_confirmed_at',
                'reservation_failed_reason',
                'payment_deadline_at',
            ]);
        });
    }
};
