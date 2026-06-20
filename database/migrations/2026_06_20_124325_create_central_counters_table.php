<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('central_counters', function (Blueprint $table) {
            $table->string('name', 100)->primary();
            $table->unsignedBigInteger('value')->default(0);
        });

        // Seed the M-Pesa account number counter
        DB::connection('central')->table('central_counters')->insert([
            'name'  => 'mpesa_account',
            'value' => 0,
        ]);
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('central_counters');
    }
};
