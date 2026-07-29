<?php

namespace App\Events\Tenant;

use App\Models\Tenant\ProductBatch;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BatchExpired
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ProductBatch $batch
    ) {}
}
