<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The shop's public page (marketplace v1): a slug for poachy.com/s/{slug}, a GPS pin for
 * directions, a WhatsApp number buyers can message, and the shop's return window.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->table('business_details', function (Blueprint $table) {
            $table->string('slug', 60)->nullable()->unique()->after('business_name');
            $table->decimal('latitude', 10, 7)->nullable()->after('county');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('whatsapp_number', 15)->nullable()->after('business_phone');
            $table->unsignedTinyInteger('return_window_days')->default(0)->after('delivery_info'); // 0 = no returns, 3 or 7
        });

        // Every existing shop gets a slug from its name so its page works straight away.
        $taken = [];
        DB::connection('central')->table('business_details')->orderBy('id')
            ->select('id', 'business_name')->each(function ($row) use (&$taken) {
                $base = Str::limit(Str::slug($row->business_name) ?: 'shop', 50, '');
                $slug = $base;
                for ($n = 2; in_array($slug, $taken, true); $n++) {
                    $slug = "{$base}-{$n}";
                }
                $taken[] = $slug;
                DB::connection('central')->table('business_details')->where('id', $row->id)->update(['slug' => $slug]);
            });
    }

    public function down(): void
    {
        Schema::connection('central')->table('business_details', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'latitude', 'longitude', 'whatsapp_number', 'return_window_days']);
        });
    }
};
