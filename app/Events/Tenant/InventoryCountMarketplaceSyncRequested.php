<?php

namespace App\Events\Tenant;

use App\DataTransferObjects\Sync\InventoryCountSyncDTO;
use App\Models\Tenant\Inventory;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InventoryCountMarketplaceSyncRequested
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly InventoryCountSyncDTO $inventoryDTO;
    public readonly string $action;
    public readonly int $priority;

    public function __construct(
        Inventory $inventory,
        string $action = 'update',
        int $priority = 3
    ) {
        $this->inventoryDTO = InventoryCountSyncDTO::fromInventory($inventory);
        $this->action = $action;
        $this->priority = $priority;
    }

    public function broadcastOn(): array
    {
        return [];
    }
}
