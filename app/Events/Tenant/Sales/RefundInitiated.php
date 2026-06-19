<?php

namespace App\Events\Tenant\Sales;

use App\Models\Tenant\SaleRefund;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RefundInitiated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly SaleRefund $refund
    ) {}
}
