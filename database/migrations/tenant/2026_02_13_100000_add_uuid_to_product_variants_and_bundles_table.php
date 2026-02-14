<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add UUID columns
        Schema::table('product_variants', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
        });

        Schema::table('product_bundles', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
        });

        // Populate UUIDs for existing records
        $this->populateUuids('product_variants');
        $this->populateUuids('product_bundles');

        // After population, make UUID non-nullable
        Schema::table('product_variants', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
        });

        Schema::table('product_bundles', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });

        Schema::table('product_bundles', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }

    /**
     * Populate UUIDs for existing records in the given table.
     */
    private function populateUuids(string $table): void
    {
        DB::table($table)
            ->whereNull('uuid')
            ->orderBy('id')
            ->chunkById(1000, function ($records) use ($table) {
                foreach ($records as $record) {
                    DB::table($table)
                        ->where('id', $record->id)
                        ->update(['uuid' => (string) Str::uuid()]);
                }
            });
    }
};
