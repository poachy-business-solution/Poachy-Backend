<?php

namespace App\Events\Tenant;

use App\DataTransferObjects\Sync\ProductVariantSyncDTO;
use App\Models\Tenant\ProductVariant;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VariantMarketplaceSyncRequested
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly ProductVariantSyncDTO $variantDTO;
    public readonly string $action;
    public readonly int $priority;

    /**
     * Create a new event instance.
     */
    public function __construct(
        ProductVariant $variant,
        string $action = 'create',
        int $priority = 3
    ) {
        $skipValidation = in_array($action, ['delete', 'deactivate']);
        $this->variantDTO = ProductVariantSyncDTO::fromVariant($variant, $skipValidation);
        $this->action = $action;
        $this->priority = $priority;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [];
    }
}
