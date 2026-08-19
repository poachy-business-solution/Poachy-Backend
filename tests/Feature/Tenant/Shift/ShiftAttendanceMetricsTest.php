<?php

namespace Tests\Feature\Tenant\Shift;

use App\Enums\Tenant\ShiftStatus;
use App\Http\Requests\Tenant\Shift\ClockInRequest;
use App\Models\Tenant\Shift;
use App\Models\Tenant\ShiftAssignment;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Carbon 3 returns SIGNED diffs, so operand order decides the sign. These
 * accessors are surfaced verbatim by ShiftAssignmentResource, so getting the
 * order wrong ships negative "minutes late" to the apps.
 *
 * No database: the models are built in memory with their relations set.
 */
class ShiftAttendanceMetricsTest extends TestCase
{
    private function assignment(
        string $scheduledStart = '08:00:00',
        string $scheduledEnd = '16:00:00',
        ?string $actualStart = null,
        ?string $actualEnd = null,
    ): ShiftAssignment {
        $shift = new Shift;
        $shift->forceFill([
            'id' => 1,
            'shift_name' => 'Morning',
            'scheduled_start_time' => $scheduledStart,
            'scheduled_end_time' => $scheduledEnd,
        ]);

        $assignment = new ShiftAssignment;
        $assignment->forceFill([
            'id' => 1,
            'shift_id' => 1,
            'store_id' => 1,
            'user_id' => 1,
            'shift_date' => '2026-08-18',
            'status' => ShiftStatus::IN_PROGRESS,
            'actual_start' => $actualStart,
            'actual_end' => $actualEnd,
        ]);
        $assignment->setRelation('shift', $shift);

        return $assignment;
    }

    public function test_minutes_late_is_positive_for_a_late_clock_in(): void
    {
        // Scheduled 08:00, arrived 08:47 -> 47 minutes late (past the 15m grace).
        $assignment = $this->assignment(actualStart: '2026-08-18 08:47:00');

        $this->assertTrue($assignment->is_late);
        $this->assertSame(47, $assignment->minutes_late);
    }

    public function test_minutes_late_is_null_when_on_time(): void
    {
        $assignment = $this->assignment(actualStart: '2026-08-18 08:05:00');

        $this->assertFalse($assignment->is_late);
        $this->assertNull($assignment->minutes_late);
    }

    public function test_minutes_early_is_positive_for_an_early_departure(): void
    {
        // Scheduled end 16:00, left 15:20 -> 40 minutes early (past the 15m grace).
        $assignment = $this->assignment(
            actualStart: '2026-08-18 08:00:00',
            actualEnd: '2026-08-18 15:20:00',
        );

        $this->assertTrue($assignment->is_early_departure);
        $this->assertSame(40, $assignment->minutes_early);
    }

    public function test_minutes_early_is_null_when_leaving_on_time(): void
    {
        $assignment = $this->assignment(
            actualStart: '2026-08-18 08:00:00',
            actualEnd: '2026-08-18 16:00:00',
        );

        $this->assertFalse($assignment->is_early_departure);
        $this->assertNull($assignment->minutes_early);
    }

    public function test_metrics_for_a_past_shift_are_not_shifted_by_a_day(): void
    {
        // Regression, pinned so it can't quietly become a same-day test: the
        // overnight check used to compare the anchored end against an
        // UN-anchored start (which resolves to today), so every historical
        // assignment gained a day and reported a ~1440-minute early departure.
        $this->travelTo('2026-09-01 10:00:00');

        $onTime = $this->assignment(
            actualStart: '2026-08-18 08:00:00',
            actualEnd: '2026-08-18 16:00:00',
        );

        $this->assertFalse($onTime->is_early_departure);
        $this->assertNull($onTime->minutes_early);

        $early = $this->assignment(
            actualStart: '2026-08-18 08:00:00',
            actualEnd: '2026-08-18 15:20:00',
        );

        $this->assertTrue($early->is_early_departure);
        $this->assertSame(40, $early->minutes_early);
    }

    public function test_overnight_shift_end_still_rolls_to_the_next_day(): void
    {
        // 20:00 -> 04:00 genuinely spans midnight: leaving at 03:00 on the 19th
        // is 60 minutes early, not a day-and-an-hour.
        $this->travelTo('2026-09-01 10:00:00');

        $assignment = $this->assignment(
            scheduledStart: '20:00:00',
            scheduledEnd: '04:00:00',
            actualStart: '2026-08-18 20:00:00',
            actualEnd: '2026-08-19 03:00:00',
        );

        $this->assertTrue($assignment->is_early_departure);
        $this->assertSame(60, $assignment->minutes_early);
    }

    public function test_actual_duration_is_a_positive_whole_number_of_minutes(): void
    {
        $assignment = $this->assignment(
            actualStart: '2026-08-18 08:00:00',
            actualEnd: '2026-08-18 16:30:00',
        );
        $assignment->calculateActualDuration();

        $this->assertSame(510, $assignment->actual_duration_minutes);
    }

    public function test_late_clock_in_message_reports_the_start_and_the_cut_off(): void
    {
        // Regression: addMinutes() mutated $scheduledStart in place, so the
        // message named the CUT-OFF while calling it the scheduled start.
        config(['shift.late_threshold_minutes' => 15]);
        $this->travelTo('2026-08-18 12:00:00');

        $assignment = $this->assignment(); // scheduled 08:00, never clocked in
        $assignment->forceFill(['status' => ShiftStatus::SCHEDULED]);

        $request = ClockInRequest::create('/clock-in', 'POST', ['opening_cash' => 500]);
        $request->setRouteResolver(fn () => new class($assignment)
        {
            public function __construct(private $assignment) {}

            public function parameter($key, $default = null)
            {
                return $key === 'assignment' ? $this->assignment : $default;
            }
        });

        $validator = Validator::make($request->all(), $request->rules());
        $request->withValidator($validator);
        $validator->passes();

        $message = $validator->errors()->first('clock_in_time');

        $this->assertStringContainsString('started at 08:00', $message);
        $this->assertStringContainsString('until 09:15', $message);
    }
}
