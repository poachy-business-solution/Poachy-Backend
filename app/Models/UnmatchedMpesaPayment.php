<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $transaction_id
 * @property float $amount
 * @property string $status open | assigned
 */
class UnmatchedMpesaPayment extends Model
{
    protected $connection = 'central';

    protected $fillable = ['transaction_id', 'amount', 'bill_ref_number', 'phone_masked', 'payer_name', 'status', 'assigned_tenant_id', 'billing_ledger_id', 'assigned_by', 'assigned_at'];

    protected function casts(): array
    {
        return ['amount' => 'float', 'assigned_at' => 'datetime'];
    }
}
