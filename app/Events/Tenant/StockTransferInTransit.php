<?php

namespace App\Events\Tenant;

use App\Models\Tenant\StockTransfer;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockTransferInTransit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public StockTransfer $transfer
    ) {}
}
