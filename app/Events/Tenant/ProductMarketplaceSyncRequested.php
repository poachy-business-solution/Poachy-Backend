<?php

namespace App\Events\Tenant;

use App\DataTransferObjects\Sync\ProductSyncDTO;
use App\Models\Tenant\Product;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductMarketplaceSyncRequested
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly ProductSyncDTO $productDTO;
    public readonly string $action;
    public readonly int $priority;

    /**
     * Create a new event instance.
     */
    public function __construct(
        Product $product,
        string $action = 'create',
        int $priority = 3
    ) {
        // Build DTO from product
        $this->productDTO = ProductSyncDTO::fromProduct($product);
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
