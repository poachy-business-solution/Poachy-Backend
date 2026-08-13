<?php

namespace App\Services\Tenant\Shift;

use App\Jobs\Tenant\SendNotificationJob;
use App\Models\Tenant\Sale;
use App\Models\Tenant\ShiftAssignment;
use App\Models\Tenant\Store;
use App\Models\Tenant\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ShiftNotificationService
{
    public function sendUpcomingShiftReminders(int $hoursBefore): int
    {
        if (! config('shift.send_shift_reminders', true)) {
            return 0;
        }

        $sent = 0;
        $now = now();
        $windowEnd = $now->copy()->addHours($hoursBefore);

        $assignments = ShiftAssignment::scheduled()
            ->with(['shift', 'store', 'user'])
            ->whereBetween('shift_date', [$now->toDateString(), $windowEnd->toDateString()])
            ->get();

        foreach ($assignments as $assignment) {
            $startsAt = $this->scheduledStartAt($assignment);

            if (! $startsAt || $startsAt->lessThanOrEqualTo($now) || $startsAt->greaterThan($windowEnd)) {
                continue;
            }

            $recipient = $assignment->user;

            if (! $this->canEmail($recipient)) {
                continue;
            }

            $cacheKey = "shift_reminder_sent:{$assignment->id}:{$startsAt->format('YmdHi')}:{$hoursBefore}";

            if (Cache::has($cacheKey)) {
                continue;
            }

            $this->dispatchEmail(
                recipient: $recipient->email,
                subject: 'Upcoming shift reminder',
                body: "Reminder: your {$this->shiftName($assignment)} shift at {$this->storeName($assignment)} starts at {$startsAt->format('M j, Y g:i A')}.",
                metadata: [
                    'notification_type' => 'shift_reminder',
                    'shift_assignment_id' => $assignment->id,
                    'shift_id' => $assignment->shift_id,
                    'store_id' => $assignment->store_id,
                    'scheduled_start_at' => $startsAt->toIso8601String(),
                ],
            );

            Cache::put($cacheKey, true, $startsAt->copy()->addDay());
            $sent++;
        }

        return $sent;
    }

    public function notifyNoShow(ShiftAssignment $assignment): void
    {
        $assignment->loadMissing(['shift', 'store', 'user']);

        $this->notifyManagersAndOwners(
            assignment: $assignment,
            subject: 'Shift marked as no-show',
            body: "{$this->employeeName($assignment)} was automatically marked as no-show for {$this->shiftName($assignment)} at {$this->storeName($assignment)} on {$assignment->shift_date->format('M j, Y')}.",
            type: 'shift_no_show',
        );
    }

    public function notifyShiftNeedsApproval(ShiftAssignment $assignment): void
    {
        $assignment->loadMissing(['shift', 'store', 'user']);

        $this->notifyManagersAndOwners(
            assignment: $assignment,
            subject: 'Shift needs approval',
            body: "{$this->employeeName($assignment)} completed {$this->shiftName($assignment)} at {$this->storeName($assignment)} and the shift now needs manager approval.",
            type: 'shift_needs_approval',
        );
    }

    public function notifyCashVariance(ShiftAssignment $assignment): void
    {
        $assignment->loadMissing(['shift', 'store', 'user']);

        $variance = number_format((float) $assignment->cash_variance, 2);
        $expectedCash = number_format((float) $assignment->expected_cash, 2);
        $closingCash = number_format((float) $assignment->closing_cash, 2);

        $this->notifyManagersAndOwners(
            assignment: $assignment,
            subject: 'Significant shift cash variance',
            body: "{$this->employeeName($assignment)} closed {$this->shiftName($assignment)} at {$this->storeName($assignment)} with a cash variance of KES {$variance}. Expected cash: KES {$expectedCash}. Closing cash: KES {$closingCash}.",
            type: 'shift_cash_variance',
            extraMetadata: [
                'cash_variance' => (float) $assignment->cash_variance,
                'expected_cash' => (float) $assignment->expected_cash,
                'closing_cash' => (float) $assignment->closing_cash,
            ],
        );
    }

    public function notifyShiftSummaryFailure(Sale $sale, \Throwable $exception): void
    {
        $assignment = $sale->shiftAssignment()->with(['shift', 'store', 'user'])->first();
        $storeId = $assignment?->store_id ?? $sale->store_id;

        $recipients = $this->managerAndOwners($storeId);

        $this->notifyRecipients(
            recipients: $recipients,
            subject: 'Shift sales summary update failed',
            body: "Poachy could not update the shift sales summary for sale {$sale->sale_number}. Error: {$exception->getMessage()}",
            metadata: [
                'notification_type' => 'shift_summary_failure',
                'sale_id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'shift_assignment_id' => $sale->shift_assignment_id,
                'store_id' => $storeId,
            ],
        );
    }

    private function notifyManagersAndOwners(
        ShiftAssignment $assignment,
        string $subject,
        string $body,
        string $type,
        array $extraMetadata = [],
    ): void {
        $this->notifyRecipients(
            recipients: $this->managerAndOwners($assignment->store_id),
            subject: $subject,
            body: $body,
            metadata: array_merge([
                'notification_type' => $type,
                'shift_assignment_id' => $assignment->id,
                'shift_id' => $assignment->shift_id,
                'store_id' => $assignment->store_id,
                'user_id' => $assignment->user_id,
            ], $extraMetadata),
        );
    }

    private function notifyRecipients(Collection $recipients, string $subject, string $body, array $metadata): void
    {
        if ($recipients->isEmpty()) {
            Log::warning('No tenant users available for shift notification', [
                'metadata' => $metadata,
            ]);

            return;
        }

        foreach ($recipients as $recipient) {
            $this->dispatchEmail(
                recipient: $recipient->email,
                subject: $subject,
                body: $body,
                metadata: array_merge($metadata, [
                    'recipient_user_id' => $recipient->id,
                ]),
            );
        }
    }

    private function dispatchEmail(string $recipient, string $subject, string $body, array $metadata): void
    {
        SendNotificationJob::dispatch(
            channel: 'email',
            recipient: $recipient,
            message: [
                'subject' => $subject,
                'body' => $body,
            ],
            metadata: $metadata,
        )->onQueue('sync-normal');
    }

    private function managerAndOwners(?int $storeId): Collection
    {
        $users = collect();

        if ($storeId && Schema::hasTable('stores') && Schema::hasColumn('stores', 'manager_id')) {
            $managerId = Store::whereKey($storeId)->value('manager_id');

            if ($managerId) {
                $manager = User::find($managerId);

                if ($manager) {
                    $users->push($manager);
                }
            }
        }

        if (Schema::hasTable('roles') && Schema::hasTable('model_has_roles')) {
            $users = $users->merge(User::role('owner')->get());
        }

        return $users
            ->filter(fn (User $user) => $this->canEmail($user))
            ->unique('id')
            ->values();
    }

    private function canEmail(?User $user): bool
    {
        if (! $user || empty($user->email)) {
            return false;
        }

        if (Schema::hasColumn('users', 'is_active') && ! $user->is_active) {
            return false;
        }

        return true;
    }

    private function scheduledStartAt(ShiftAssignment $assignment): ?Carbon
    {
        if (! $assignment->shift?->scheduled_start_time) {
            return null;
        }

        return Carbon::parse($assignment->shift->scheduled_start_time)
            ->setDateFrom($assignment->shift_date);
    }

    private function shiftName(ShiftAssignment $assignment): string
    {
        return $assignment->shift?->shift_name ?? 'Shift #'.$assignment->shift_id;
    }

    private function storeName(ShiftAssignment $assignment): string
    {
        return $assignment->store?->name ?? 'Store #'.$assignment->store_id;
    }

    private function employeeName(ShiftAssignment $assignment): string
    {
        return $assignment->user?->name ?? 'User #'.$assignment->user_id;
    }
}
