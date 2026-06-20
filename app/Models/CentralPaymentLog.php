<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CentralPaymentLog extends Model
{
    protected $connection = 'central';

    protected $table = 'central_payment_logs';

    /** Append-only — no updated_at. */
    public $timestamps = false;

    protected $fillable = [
        'payable_type',
        'payable_id',
        'event',
        'tenant_id',
        'customer_id',
        'amount',
        'customer_phone',
        'transaction_reference',
        'provider_reference',
        'result_code',
        'result_description',
        'raw_payload',
        'ip_address',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'      => 'decimal:2',
            'raw_payload' => 'array',
            'created_at'  => 'datetime',
        ];
    }

    /**
     * Record a payment event in a single call.
     *
     * @param  array<string, mixed>  $data
     */
    public static function record(
        string $payableType,
        int $payableId,
        string $event,
        array $data = [],
    ): self {
        return self::create(array_merge([
            'payable_type' => $payableType,
            'payable_id'   => $payableId,
            'event'        => $event,
            'ip_address'   => request()->ip(),
            'created_at'   => now(),
        ], $data));
    }
}
