<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Reserve & collect adds two central→shop sync actions: hold the stock, release it. */
return new class extends Migration
{
    private const BEFORE = "'create','update','payment_confirmed','delivery_update','review_posted','cancel','reserve_inventory','release_reservation'";

    private const AFTER = self::BEFORE.",'reservation_hold','reservation_release'";

    public function up(): void
    {
        DB::connection('central')->statement('ALTER TABLE `sync_queue_outbound` MODIFY `action` ENUM('.self::AFTER.') NOT NULL');
    }

    public function down(): void
    {
        DB::connection('central')->table('sync_queue_outbound')->whereIn('action', ['reservation_hold', 'reservation_release'])->delete();
        DB::connection('central')->statement('ALTER TABLE `sync_queue_outbound` MODIFY `action` ENUM('.self::BEFORE.') NOT NULL');
    }
};
