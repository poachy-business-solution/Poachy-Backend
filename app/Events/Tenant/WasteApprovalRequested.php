<?php

namespace App\Events\Tenant;

use App\Models\Tenant\InventoryWaste;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WasteApprovalRequested
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public InventoryWaste $waste
    ) {}
}
