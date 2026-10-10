<?php

namespace App\Services\Tenant\Shift;

use App\Enums\Tenant\ShiftStatus;
use App\Models\Tenant\Shift;
use App\Models\Tenant\ShiftAssignment;
use App\Models\Tenant\TenantConfiguration;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Till sessions for shops that don't roster staff.
 *
 * A till session is an ordinary ShiftAssignment, so sales attach to it and
 * clock-out cash-up works unchanged. Selling is never blocked: a session
 * starts automatically with a person's first sale at a branch
 * ({@see sessionForSale()}), its float carried forward from the branch's last
 * close ({@see carriedFloat()}). Sessions may close without a count unless the
 * shop requires one, and any left open close themselves after local midnight
 * ({@see autoCloseStaleSessions()}). When the user has a scheduled assignment
 * at the store today it is used, so rostered shops keep their attendance data.
 */
class TillService
{
    public const TILL_SHIFT_NAME = 'Till session';

    /** TenantConfiguration key: the shop's IANA timezone. */
    public const TIMEZONE_CONFIG_KEY = 'timezone';

    /** Where the pilot shops are; used until a shop sets its own timezone. */
    public const DEFAULT_TIMEZONE = 'Africa/Nairobi';

    /** TenantConfiguration key: staff must count the cash when closing. */
    public const REQUIRE_COUNT_CONFIG_KEY = 'till_require_cash_count';

    public function __construct(protected ShiftAssignmentService $assignmentService) {}

    /**
     * Open (or return the already-open) till for the user at the store.
     *
     * With an opening cash figure the float is counted; without one it is
     * carried forward from the branch's last close.
     *
     * @return array{assignment: ShiftAssignment, already_open: bool}
     *
     * @throws ValidationException
     */
    public function open(int $userId, int $storeId, ?float $openingCash, ?string $notes = null): array
    {
        $open = $this->openSessionFor($userId);

        if ($open !== null) {
            if ((int) $open->store_id !== $storeId) {
                throw ValidationException::withMessages([
                    'store_id' => 'You already have a till open at '.($open->store?->name ?? 'another store').'. Close it before opening one here.',
                ]);
            }

            return ['assignment' => $open->load(['shift', 'store', 'user']), 'already_open' => true];
        }

        return [
            'assignment' => $this->start($userId, $storeId, $openingCash, false, now(), $notes),
            'already_open' => false,
        ];
    }

    /**
     * The till session a sale belongs to, starting one if the seller has none
     * — selling is never blocked on the till.
     *
     * An offline sale ([$soldAt] set) goes to the session that was open at
     * that moment, even if closed since; failing that, to today's open session
     * (moved back to start at the sale); failing that, a new session starting
     * at the sale. Sales from an earlier day with no session stay unattached.
     */
    public function sessionForSale(int $userId, int $storeId, ?Carbon $soldAt = null): ?ShiftAssignment
    {
        if ($soldAt !== null) {
            $covering = ShiftAssignment::query()
                ->where('user_id', $userId)
                ->where('store_id', $storeId)
                ->whereIn('status', [ShiftStatus::IN_PROGRESS, ShiftStatus::COMPLETED])
                ->where('actual_start', '<=', $soldAt)
                ->where(fn ($query) => $query->whereNull('actual_end')->orWhere('actual_end', '>=', $soldAt))
                ->orderByDesc('actual_start')
                ->first();

            if ($covering !== null) {
                return $covering;
            }

            if ($this->localDate($soldAt) !== $this->localToday()) {
                return null;
            }
        }

        $open = $this->openSessionFor($userId);

        if ($open !== null && (int) $open->store_id === $storeId) {
            if ($soldAt !== null && $open->actual_start?->greaterThan($soldAt)) {
                $open->update(['actual_start' => $soldAt]);
            }

            return $open;
        }

        if ($open !== null) {
            $this->closeAutomatically($open, now());
        }

        return $this->start($userId, $storeId, null, true, $soldAt ?? now());
    }

    /**
     * The user's till session open today (shop-local), at any store.
     */
    public function openSessionFor(int $userId, ?int $storeId = null): ?ShiftAssignment
    {
        return ShiftAssignment::query()
            ->where('user_id', $userId)
            ->where('status', ShiftStatus::IN_PROGRESS)
            ->whereDate('shift_date', $this->localToday())
            ->when($storeId !== null, fn ($query) => $query->where('store_id', $storeId))
            ->with('store')
            ->latest('actual_start')
            ->first();
    }

    /**
     * Float for a new session at the branch: the last close's counted cash
     * minus what was banked or taken home. An uncounted close carries its
     * expected cash as an estimate; with no close at all the float is unknown.
     *
     * @return array{amount: float|null, source: string}
     */
    public function carriedFloat(int $storeId): array
    {
        $last = ShiftAssignment::query()
            ->where('store_id', $storeId)
            ->where('status', ShiftStatus::COMPLETED)
            ->whereNotNull('actual_end')
            ->orderByDesc('actual_end')
            ->orderByDesc('id')
            ->first();

        if ($last === null) {
            return ['amount' => null, 'source' => ShiftAssignment::FLOAT_NOT_COUNTED];
        }

        if ($this->floatAlreadyTaken($storeId, $last)) {
            return ['amount' => 0.0, 'source' => ShiftAssignment::FLOAT_CARRIED_FORWARD];
        }

        $banked = (float) ($last->cash_banked ?? 0);

        if ($last->closing_cash !== null) {
            return [
                'amount' => max(0, round((float) $last->closing_cash - $banked, 2)),
                'source' => ShiftAssignment::FLOAT_CARRIED_FORWARD,
            ];
        }

        if ($last->is_float_known) {
            return [
                'amount' => max(0, round((float) $last->expected_cash - $banked, 2)),
                'source' => ShiftAssignment::FLOAT_ESTIMATED,
            ];
        }

        return ['amount' => null, 'source' => ShiftAssignment::FLOAT_NOT_COUNTED];
    }

    /**
     * Whether a session still open at the branch started after the last close
     * and so already holds that close's float: the same cash can't open two
     * tills.
     */
    protected function floatAlreadyTaken(int $storeId, ShiftAssignment $lastClose): bool
    {
        return ShiftAssignment::query()
            ->where('store_id', $storeId)
            ->where('status', ShiftStatus::IN_PROGRESS)
            ->where('id', '!=', $lastClose->id)
            ->where(fn ($query) => $query->whereNull('actual_start')->orWhere('actual_start', '>=', $lastClose->actual_end))
            ->whereNotNull('opening_cash')
            ->exists();
    }

    /**
     * Close every till session left open from an earlier shop-local day, as
     * of that day's local midnight, without a count.
     *
     * @return int sessions closed
     */
    public function autoCloseStaleSessions(): int
    {
        $stale = ShiftAssignment::query()
            ->where('status', ShiftStatus::IN_PROGRESS)
            ->whereDate('shift_date', '<', $this->localToday())
            ->get();

        foreach ($stale as $session) {
            $endOfDay = Carbon::parse($session->shift_date->toDateString(), $this->timezone())
                ->endOfDay()
                ->utc();
            $this->closeAutomatically($session, $endOfDay);
        }

        return $stale->count();
    }

    /**
     * Reopen a till closed by mistake today: back to open, count and banked
     * cleared. Refused when the person has already started a newer session
     * (selling after a close starts one automatically).
     *
     * @throws ValidationException
     */
    public function reopen(ShiftAssignment $session, string $reopenedBy): ShiftAssignment
    {
        if ($session->status !== ShiftStatus::COMPLETED) {
            throw ValidationException::withMessages(['status' => 'Only a closed till can be reopened.']);
        }

        if ($session->shift_date->toDateString() !== $this->localToday()) {
            throw ValidationException::withMessages(['status' => "Only today's tills can be reopened."]);
        }

        if ($this->openSessionFor($session->user_id) !== null) {
            throw ValidationException::withMessages([
                'status' => 'This person has started selling again on a new till. Close that one instead.',
            ]);
        }

        $session->forceFill([
            'status' => ShiftStatus::IN_PROGRESS,
            'actual_end' => null,
            'actual_duration_minutes' => null,
            'closing_cash' => null,
            'cash_banked' => null,
            'cash_variance_reason' => null,
            'closed_automatically' => false,
            'approved_by' => null,
            'approved_at' => null,
            'notes' => trim(($session->notes ? $session->notes."\n\n" : '')."Reopened by {$reopenedBy} at ".now()->setTimezone($this->timezone())->format('H:i')),
        ])->saveQuietly();

        return $session->fresh(['shift', 'store', 'user']);
    }

    /**
     * Whether staff must count the cash to close (owners and managers may
     * always skip).
     */
    public function cashCountRequired(): bool
    {
        return (bool) TenantConfiguration::get(self::REQUIRE_COUNT_CONFIG_KEY, false);
    }

    public function setCashCountRequired(bool $required): void
    {
        TenantConfiguration::set(self::REQUIRE_COUNT_CONFIG_KEY, $required, 'till');
    }

    public function timezone(): string
    {
        $timezone = TenantConfiguration::get(self::TIMEZONE_CONFIG_KEY);

        return is_string($timezone) && in_array($timezone, timezone_identifiers_list(), true)
            ? $timezone
            : self::DEFAULT_TIMEZONE;
    }

    /**
     * Today's date in the shop's timezone (the server runs in UTC).
     */
    public function localToday(): string
    {
        return $this->localDate(now());
    }

    public function localDate(Carbon $moment): string
    {
        return $moment->copy()->setTimezone($this->timezone())->toDateString();
    }

    protected function start(int $userId, int $storeId, ?float $openingCash, bool $automatic, Carbon $startedAt, ?string $notes = null): ShiftAssignment
    {
        $float = $openingCash !== null
            ? ['amount' => $openingCash, 'source' => ShiftAssignment::FLOAT_COUNTED]
            : $this->carriedFloat($storeId);

        $assignment = $this->scheduledAssignmentForToday($userId, $storeId)
            ?? $this->createTillAssignment($userId, $storeId);

        $assignment = $this->assignmentService->clockIn($assignment, (float) ($float['amount'] ?? 0), $notes);

        $assignment->update([
            'opening_cash' => $float['amount'],
            'opening_cash_source' => $float['source'],
            'started_automatically' => $automatic,
            'actual_start' => $startedAt,
        ]);

        return $assignment->fresh(['shift', 'store', 'user']);
    }

    protected function closeAutomatically(ShiftAssignment $session, Carbon $at): void
    {
        $session->update([
            'status' => ShiftStatus::COMPLETED,
            'actual_end' => $at->max($session->actual_start ?? $at),
            'closed_automatically' => true,
        ]);
    }

    protected function scheduledAssignmentForToday(int $userId, int $storeId): ?ShiftAssignment
    {
        return ShiftAssignment::query()
            ->where('user_id', $userId)
            ->where('store_id', $storeId)
            ->where('status', ShiftStatus::SCHEDULED)
            ->whereDate('shift_date', $this->localToday())
            ->orderBy('id')
            ->first();
    }

    protected function createTillAssignment(int $userId, int $storeId): ShiftAssignment
    {
        return $this->assignmentService->assignShift([
            'shift_id' => $this->tillShiftFor($storeId)->id,
            'store_id' => $storeId,
            'user_id' => $userId,
            'shift_date' => $this->localToday(),
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
