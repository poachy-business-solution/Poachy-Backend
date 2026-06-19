<?php

namespace App\Observers\Tenant;

use App\Models\Tenant\Shift;
use Illuminate\Support\Facades\Auth;

class ShiftObserver
{
    /**
     * Handle the Shift "creating" event.
     */
    public function creating(Shift $shift): void
    {
        // Ensure duration is calculated
        if ($shift->scheduled_start_time && $shift->scheduled_end_time && !$shift->duration_minutes) {
            $shift->calculateDuration();
        }
    }

    /**
     * Handle the Shift "created" event.
     */
    public function created(Shift $shift): void
    {
        $this->logAudit($shift, 'created', null, $shift->toArray());
    }

    /**
     * Handle the Shift "updating" event.
     */
    public function updating(Shift $shift): void
    {
        // Recalculate duration if times changed
        if ($shift->isDirty(['scheduled_start_time', 'scheduled_end_time'])) {
            $shift->calculateDuration();
        }
    }

    /**
     * Handle the Shift "updated" event.
     */
    public function updated(Shift $shift): void
    {
        $this->logAudit($shift, 'updated', $shift->getOriginal(), $shift->getChanges());
    }

    /**
     * Handle the Shift "deleted" event.
     */
    public function deleted(Shift $shift): void
    {
        $this->logAudit($shift, 'deleted', $shift->toArray(), null);
    }

    /**
     * Handle the Shift "restored" event.
     */
    public function restored(Shift $shift): void
    {
        $this->logAudit($shift, 'restored', null, $shift->toArray());
    }

    /**
     * Handle the Shift "force deleted" event.
     */
    public function forceDeleted(Shift $shift): void
    {
        $this->logAudit($shift, 'force_deleted', $shift->toArray(), null);
    }

    /**
     * Log audit trail
     */
    protected function logAudit(Shift $shift, string $action, ?array $oldValues, ?array $newValues): void
    {
        // Create audit log entry
        // \App\Models\Tenant\AuditLog::create([
        //     'user_id' => Auth::id(),
        //     'user_name' => Auth::user()?->name,
        //     'ip_address' => request()->ip(),
        //     'action' => $action,
        //     'model_type' => Shift::class,
        //     'model_id' => $shift->id,
        //     'old_values' => $oldValues,
        //     'new_values' => $newValues,
        //     'description' => $this->generateDescription($action, $shift),
        //     'tags' => 'shift_management',
        // ]);
    }

    /**
     * Generate human-readable description
     */
    protected function generateDescription(string $action, Shift $shift): string
    {
        $user = Auth::user();
        $userName = $user ? $user->name : 'System';

        return match ($action) {
            'created' => "{$userName} created shift '{$shift->shift_name}'",
            'updated' => "{$userName} updated shift '{$shift->shift_name}'",
            'deleted' => "{$userName} deleted shift '{$shift->shift_name}'",
            'restored' => "{$userName} restored shift '{$shift->shift_name}'",
            'force_deleted' => "{$userName} permanently deleted shift '{$shift->shift_name}'",
            default => "{$userName} performed {$action} on shift '{$shift->shift_name}'",
        };
    }
}
