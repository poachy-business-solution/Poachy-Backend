<?php

namespace App\Events\Tenant;

use App\Models\Tenant\ExpiryAlert;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExpiryAlertCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ExpiryAlert $alert
    ) {}
}
