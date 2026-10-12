<?php

namespace Database\Seeders;

use App\Models\BillingPrice;
use Illuminate\Database\Seeder;

/**
 * The launch price list (agreed 12 Oct 2026). Later changes are new rows with a later
 * effective date — never edit this one.
 */
class BillingPriceSeeder extends Seeder
{
    public function run(): void
    {
        BillingPrice::query()->firstOrCreate(
            ['effective_from' => '2026-01-01'],
            [
                'first_branch_day' => 100,
                'extra_branch_day' => 50,
                'first_branch_cap' => 2500,
                'extra_branch_cap' => 1250,
                'trial_days' => 30,
                'min_topup' => 10,
                'low_balance_days' => 3,
                'grace_days' => 1,
                'notes' => 'Launch prices',
                'created_by' => 'seeder',
            ],
        );
    }
}
