<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Set when the person chose their own password (self sign-up): no forced change on first login.
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('password_chosen_at')->nullable()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('password_chosen_at');
        });
    }
};
