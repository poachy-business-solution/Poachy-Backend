<?php

namespace Database\Seeders;

use App\Models\Tenant\ExpenseCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Everyday expense categories for a Kenyan shop, so a new shop can record expenses on day one.
 * Shops add their own on top. Safe to run again (matches by name).
 */
class ExpenseCategorySeeder extends Seeder
{
    /** name => can repeat (rent, salaries…) */
    public const DEFAULTS = [
        'Rent' => true,
        'Salaries & wages' => true,
        'Transport' => false,
        'Electricity & water' => true,
        'Airtime & internet' => true,
        'Cleaning & supplies' => false,
        'Repairs' => false,
        'Licences & permits' => true,
        'Other' => false,
    ];

    public function run(): void
    {
        $order = 0;
        foreach (self::DEFAULTS as $name => $recurring) {
            ExpenseCategory::query()->firstOrCreate(['name' => $name], [
                'code' => strtoupper(Str::slug($name, '_')),
                'is_recurring_eligible' => $recurring,
                'requires_receipt' => false,
                'requires_approval' => false,
                'is_active' => true,
                'display_order' => $order++,
            ]);
        }
    }
}
