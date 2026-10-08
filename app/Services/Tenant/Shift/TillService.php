<?php

namespace App\Services\Tenant\Shift;

use App\Enums\Tenant\ShiftStatus;
use App\Models\Tenant\Shift;
use App\Models\Tenant\ShiftAssignment;
use Illuminate\Validation\ValidationException;

/**
 * Open-till flow for shops that don't roster staff.
 *
 * A till session is an ordinary ShiftAssignment, so sales attach to it and
 * clock-out cash-up works unchanged. The difference is how it starts: no
 * pre-scheduled assignment and no clock-in time window are required. When the
 * user does have a scheduled assignment at the store today it is used, so
 * rostered shops keep their attendance data.
 */
class TillService
{
    public const TILL_SHIFT_NAME = 'Till session';

    public function __construct(protected ShiftAssignmentService $assignmentService) {}

    /**
     * Open (or return the already-open) till for the user at the store.
     *
     * @return array{assignment: ShiftAssignment, already_open: bool}
     *
     * @throws ValidationException
     */
    public function open(int $userId, int $storeId, float $openingCash, ?string $notes = null): array
    {
        $open = ShiftAssignment::query()
            ->where('user_id', $userId)
            ->where('status', ShiftStatus::IN_PROGRESS)
            ->whereDate('shift_date', today())
            ->with('store')
            ->first();

        if ($open !== null) {
            if ((int) $open->store_id !== $storeId) {
                throw ValidationException::withMessages([
                    'store_id' => 'You already have a till open at '.($open->store?->name ?? 'another store').'. Close it before opening one here.',
                ]);
            }

            return ['assignment' => $open->load(['shift', 'store', 'user']), 'already_open' => true];
        }

        $assignment = $this->scheduledAssignmentForToday($userId, $storeId)
            ?? $this->createTillAssignment($userId, $storeId);

        return [
            'assignment' => $this->assignmentService->clockIn($assignment, $openingCash, $notes),
            'already_open' => false,
        ];
    }

    protected function scheduledAssignmentForToday(int $userId, int $storeId): ?ShiftAssignment
    {
        return ShiftAssignment::query()
            ->where('user_id', $userId)
            ->where('store_id', $storeId)
            ->where('status', ShiftStatus::SCHEDULED)
            ->whereDate('shift_date', today())
            ->orderBy('id')
            ->first();
    }

    /**
     * @throws ValidationException
     */
    protected function createTillAssignment(int $userId, int $storeId): ShiftAssignment
    {
        $tillShift = $this->tillShiftFor($storeId);

        $closedToday = ShiftAssignment::query()
            ->where('user_id', $userId)
            ->where('shift_id', $tillShift->id)
            ->whereDate('shift_date', today())
            ->exists();

        if ($closedToday) {
            throw ValidationException::withMessages([
                'store_id' => 'Your till for today has already been closed. Ask a manager if it needs reopening.',
            ]);
        }

        return $this->assignmentService->assignShift([
            'shift_id' => $tillShift->id,
            'store_id' => $storeId,
            'user_id' => $userId,
            'shift_date' => today(),
            'status' => ShiftStatus::SCHEDULED,
        ]);
    }

    /**
     * The store's all-day shift definition that unscheduled till sessions hang off.
     */
    protected function tillShiftFor(int $storeId): Shift
    {
        return Shift::query()->firstOrCreate(
            ['store_id' => $storeId, 'shift_name' => self::TILL_SHIFT_NAME],
            ['scheduled_start_time' => '00:00', 'scheduled_end_time' => '23:59', 'is_active' => true],
        );
    }
}
