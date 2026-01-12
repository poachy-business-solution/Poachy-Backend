<?php

namespace App\Events\Tenant;

use App\Models\Tenant\StockAlert;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockAlertResolved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public StockAlert $alert
    ) {}
}
