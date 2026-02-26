<?php

namespace App\Events\Tenant;

use App\DataTransferObjects\Sync\DeliveryZoneSyncDTO;
use App\Models\Tenant\TenantDeliveryZone;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeliveryZoneMarketplaceSyncRequested
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly DeliveryZoneSyncDTO $zoneDTO;
    public readonly string $action;
    public readonly int $priority;

    public function __construct(
        TenantDeliveryZone $zone,
        string $action = 'create',
        int $priority = 3
    ) {
        $this->zoneDTO = DeliveryZoneSyncDTO::fromModel($zone);
        $this->action = $action;
        $this->priority = $priority;
    }

    public function broadcastOn(): array
    {
        return [];
    }
}
