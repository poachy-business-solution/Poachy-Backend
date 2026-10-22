<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One sale line per order. The (order_id, type, refund_id) key never stopped a second SALE
     * line because MySQL treats NULL refund_ids as distinct; refund_key is 0 for those.
     */
    public function up(): void
    {
        Schema::connection('central')->table('marketplace_payout_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('refund_key')->storedAs('coalesce(`refund_id`, 0)')->after('refund_id');
        });

        Schema::connection('central')->table('marketplace_payout_lines', function (Blueprint $table) {
            $table->dropUnique(['order_id', 'type', 'refund_id']);
            $table->unique(['order_id', 'type', 'refund_key']);
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('marketplace_payout_lines', function (Blueprint $table) {
            $table->dropUnique(['order_id', 'type', 'refund_key']);
            $table->unique(['order_id', 'type', 'refund_id']);
        });

        Schema::connection('central')->table('marketplace_payout_lines', function (Blueprint $table) {
            $table->dropColumn('refund_key');
        });
    }
};
