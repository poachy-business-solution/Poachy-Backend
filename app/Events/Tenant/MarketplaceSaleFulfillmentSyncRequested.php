<?php

namespace App\Events\Tenant;

use App\DataTransferObjects\Sync\MarketplaceFulfillmentSyncDTO;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MarketplaceSaleFulfillmentSyncRequested
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly MarketplaceFulfillmentSyncDTO $fulfillmentDTO;

    public readonly string $action;

    public readonly int $priority;

    public function __construct(
        MarketplaceFulfillmentSyncDTO $fulfillmentDTO,
        string $action = 'update',
        int $priority = 2
    ) {
        $this->fulfillmentDTO = $fulfillmentDTO;
        $this->action = $action;
        $this->priority = $priority;
    }

    public function broadcastOn(): array
    {
        return [];
    }
}
