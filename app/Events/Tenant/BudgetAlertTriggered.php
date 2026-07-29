<?php

namespace App\Events\Tenant;

use App\Models\Tenant\Budget;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BudgetAlertTriggered
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Budget $budget
    ) {}
}
