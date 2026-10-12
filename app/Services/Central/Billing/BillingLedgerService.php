<?php

namespace App\Services\Central\Billing;

use App\Enums\Central\BillingLedgerType;
use App\Models\BillingAccount;
use App\Models\BillingLedgerEntry;
use Illuminate\Support\Facades\DB;

/**
 * The only way a billing balance changes: lock the account, move the balance, append a ledger
 * line — in one transaction. An idempotency key makes a repeated call return the first entry.
 */
class BillingLedgerService
{
    /**
     * @param  float  $amount  > 0 credits the balance, < 0 debits it
     * @param  array<string, mixed>  $meta
     */
    public function post(
        BillingAccount $account,
        BillingLedgerType $type,
        float $amount,
        string $description,
        string $source,
        ?string $reference = null,
        ?string $idempotencyKey = null,
        string $createdBy = 'system',
        array $meta = [],
    ): BillingLedgerEntry {
        return DB::connection('central')->transaction(function () use ($account, $type, $amount, $description, $source, $reference, $idempotencyKey, $createdBy, $meta) {
            if ($idempotencyKey !== null) {
                $existing = BillingLedgerEntry::query()->where('idempotency_key', $idempotencyKey)->first();
                if ($existing !== null) {
                    return $existing;
                }
            }

            /** @var BillingAccount $locked */
            $locked = BillingAccount::query()->whereKey($account->getKey())->lockForUpdate()->firstOrFail();
            $balance = round((float) $locked->balance + $amount, 2);
            $locked->forceFill(['balance' => $balance])->save();
            $account->setRawAttributes($locked->getAttributes(), true);

            return BillingLedgerEntry::query()->create([
                'tenant_id' => $locked->tenant_id,
                'billing_account_id' => $locked->id,
                'type' => $type,
                'amount' => round($amount, 2),
                'balance_after' => $balance,
                'description' => $description,
                'source' => $source,
                'reference' => $reference,
                'idempotency_key' => $idempotencyKey,
                'created_by' => $createdBy,
                'meta' => $meta === [] ? null : $meta,
            ]);
        });
    }
}
