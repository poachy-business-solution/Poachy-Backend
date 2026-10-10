<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Automatic till sessions: a session starts with a person's first sale,
     * carries its float forward from the last count, may close without a
     * count, and closes itself at local midnight. A person can have several
     * till sessions on one day (closed at lunch, selling again after), so the
     * one-assignment-per-shift-per-day unique index becomes a plain index;
     * rostered duplicates are refused in StoreShiftAssignmentRequest instead.
     */
    public function up(): void
    {
        Schema::table('shift_assignments', function (Blueprint $table) {
            $table->string('opening_cash_source', 20)->nullable()->after('opening_cash');
            $table->decimal('cash_banked', 15, 2)->nullable()->after('closing_cash');
            $table->boolean('started_automatically')->default(false)->after('status');
            $table->boolean('closed_automatically')->default(false)->after('started_automatically');
            $table->index(['user_id', 'shift_date', 'shift_id'], 'idx_user_shift_per_day');
        });

        Schema::table('shift_assignments', function (Blueprint $table) {
            $table->dropUnique('unique_user_shift_per_day');
        });
    }

    public function down(): void
    {
        Schema::table('shift_assignments', function (Blueprint $table) {
            $table->unique(['user_id', 'shift_date', 'shift_id'], 'unique_user_shift_per_day');
        });

        Schema::table('shift_assignments', function (Blueprint $table) {
            $table->dropIndex('idx_user_shift_per_day');
            $table->dropColumn(['opening_cash_source', 'cash_banked', 'started_automatically', 'closed_automatically']);
        });
    }
};
