<?php

namespace App\Events\Tenant;

use App\Models\Tenant\Expense;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExpenseCreatedPendingApproval
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Expense $expense
    ) {}
}
