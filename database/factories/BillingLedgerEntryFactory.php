<?php

namespace Database\Factories;

use App\Enums\Central\BillingLedgerType;
use App\Models\BillingAccount;
use App\Models\BillingLedgerEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillingLedgerEntry>
 */
class BillingLedgerEntryFactory extends Factory
{
    protected $model = BillingLedgerEntry::class;

    public function definition(): array
    {
        return [
            'billing_account_id' => BillingAccount::factory(),
            'tenant_id' => fn (array $a) => BillingAccount::query()->find($a['billing_account_id'])?->tenant_id,
            'type' => BillingLedgerType::Topup,
            'amount' => 500,
            'balance_after' => 500,
            'description' => 'M-Pesa top-up',
            'source' => 'mpesa_c2b',
            'reference' => 'FK'.strtoupper(fake()->bothify('########')),
            'created_by' => 'system',
        ];
    }
}
