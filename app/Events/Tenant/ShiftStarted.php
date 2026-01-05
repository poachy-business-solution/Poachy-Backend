<?php

namespace App\Events\Tenant;

use App\Models\Tenant\ShiftAssignment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ShiftStarted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ShiftAssignment $assignment;

    /**
     * Create a new event instance.
     */
    public function __construct(ShiftAssignment $assignment)
    {
        $this->assignment = $assignment;
    }
}
