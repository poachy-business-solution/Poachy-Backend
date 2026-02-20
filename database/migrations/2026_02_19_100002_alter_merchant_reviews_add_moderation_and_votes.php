<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->table('merchant_reviews', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable()->after('status');
            $table->unsignedBigInteger('moderated_by')->nullable()->after('moderated_at');
            $table->integer('helpful_count')->default(0)->after('moderated_by');
            $table->integer('not_helpful_count')->default(0)->after('helpful_count');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('merchant_reviews', function (Blueprint $table) {
            $table->dropColumn(['rejection_reason', 'moderated_by', 'helpful_count', 'not_helpful_count']);
        });
    }
};
