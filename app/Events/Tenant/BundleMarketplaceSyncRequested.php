<?php

namespace App\Events\Tenant;

use App\DataTransferObjects\Sync\BundleSyncDTO;
use App\Models\Tenant\ProductBundle;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BundleMarketplaceSyncRequested
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly BundleSyncDTO $bundleDTO;
    public readonly string $action;
    public readonly int $priority;

    /**
     * Create a new event instance.
     */
    public function __construct(
        ProductBundle $bundle,
        string $action = 'create',
        int $priority = 3
    ) {
        $skipValidation = in_array($action, ['delete', 'deactivate']);
        $this->bundleDTO = BundleSyncDTO::fromBundle($bundle, $skipValidation);
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
