<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $transaction_id
 * @property float $amount
 * @property string $source paybill | marketplace
 * @property string $status open | assigned | returned
 */
class UnmatchedMpesaPayment extends Model
{
    protected $connection = 'central';

    protected $fillable = ['transaction_id', 'source', 'amount', 'bill_ref_number', 'order_number', 'phone_masked', 'payer_name', 'note', 'status', 'assigned_tenant_id', 'billing_ledger_id', 'assigned_by', 'assigned_at', 'returned_by', 'returned_at'];

    protected function casts(): array
    {
        return ['amount' => 'float', 'assigned_at' => 'datetime', 'returned_at' => 'datetime'];
    }
}
