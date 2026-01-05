<?php

namespace App\Services\Tenant\Shift;

use App\Models\Tenant\ShiftAssignment;
use App\Models\Tenant\ShiftSwapRequest;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShiftSwapService
{
    /**
     * Execute a shift swap (manager creates and executes in one action)
     */
    public function executeSwap(array $data): ShiftSwapRequest
    {
        try {
            DB::beginTransaction();

            $requesterAssignment = ShiftAssignment::findOrFail($data['requester_assignment_id']);
            $targetAssignment = ShiftAssignment::findOrFail($data['target_assignment_id']);

            // Save BOTH user IDs before any updates
            $originalRequesterUserId = $requesterAssignment->user_id;
            $originalTargetUserId = $targetAssignment->user_id;

            // Validate: Cannot swap with yourself
            if ($originalRequesterUserId === $originalTargetUserId) {
                throw new \InvalidArgumentException('Cannot swap shifts with the same user. Both assignments belong to the same employee.');
            }

            // Validate: Check if target user already has a shift on requester's date
            $conflictingAssignment = ShiftAssignment::where('user_id', $originalTargetUserId)
                ->where('shift_date', $requesterAssignment->shift_date)
                ->where('shift_id', $requesterAssignment->shift_id)
                ->where('id', '!=', $requesterAssignment->id)
                ->where('status', '!=', 'cancelled')
                ->first();

            if ($conflictingAssignment) {
                throw new \InvalidArgumentException(
                    "Cannot swap: Target user (ID: {$originalTargetUserId}) already has a shift assignment (ID: {$conflictingAssignment->id}) on {$requesterAssignment->shift_date->format('Y-m-d')}. "
                        . "Employee cannot have duplicate shifts on the same date."
                );
            }

            // Validate: Check if requester user already has a shift on target's date
            $conflictingAssignment = ShiftAssignment::where('user_id', $originalRequesterUserId)
                ->where('shift_date', $targetAssignment->shift_date)
                ->where('shift_id', $targetAssignment->shift_id)
                ->where('id', '!=', $targetAssignment->id)
                ->where('status', '!=', 'cancelled')
                ->first();

            if ($conflictingAssignment) {
                throw new \InvalidArgumentException(
                    "Cannot swap: Requester user (ID: {$originalRequesterUserId}) already has a shift assignment (ID: {$conflictingAssignment->id}) on {$targetAssignment->shift_date->format('Y-m-d')}. "
                        . "Employee cannot have duplicate shifts on the same date."
                );
            }

            // Temporarily disable observer to avoid validation conflicts during swap
            ShiftAssignment::withoutEvents(function () use ($requesterAssignment, $targetAssignment, $originalRequesterUserId, $originalTargetUserId) {
                // Swap the user_ids on assignments
                $requesterAssignment->update([
                    'user_id' => $originalTargetUserId,
                    'notes' => $requesterAssignment->notes
                        ? $requesterAssignment->notes . "\n\nSwapped with user #{$originalTargetUserId}"
                        : "Swapped with user #{$originalTargetUserId}",
                ]);

                $targetAssignment->update([
                    'user_id' => $originalRequesterUserId,
                    'notes' => $targetAssignment->notes
                        ? $targetAssignment->notes . "\n\nSwapped with user #{$originalRequesterUserId}"
                        : "Swapped with user #{$originalRequesterUserId}",
                ]);
            });

            // Create swap record with ORIGINAL user IDs
            $swapRequest = ShiftSwapRequest::create([
                'requester_assignment_id' => $data['requester_assignment_id'],
                'target_assignment_id' => $data['target_assignment_id'],
                'requester_id' => $originalRequesterUserId,
                'target_user_id' => $originalTargetUserId,
                'reason' => $data['reason'],
                'manager_id' => $data['manager_id'],
                'manager_note' => $data['manager_note'] ?? null,
                'swapped_at' => now(),
            ]);

            $this->clearSwapCache();

            DB::commit();

            Log::info('Shift swap executed', [
                'swap_request_id' => $swapRequest->id,
                'requester_assignment_id' => $requesterAssignment->id,
                'target_assignment_id' => $targetAssignment->id,
                'original_requester_user_id' => $originalRequesterUserId,
                'original_target_user_id' => $originalTargetUserId,
                'manager_id' => $data['manager_id'],
                'tenant_id' => tenant()->id,
            ]);

            return $swapRequest->load(['requesterAssignment', 'targetAssignment', 'requester', 'targetUser', 'manager']);
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();

            // Handle duplicate entry errors with better messaging
            if (str_contains($e->getMessage(), 'Duplicate entry') || str_contains($e->getMessage(), 'unique_user_shift_per_day')) {
                Log::error('Shift swap failed due to duplicate constraint', [
                    'error' => $e->getMessage(),
                    'data' => $data,
                    'tenant_id' => tenant()->id,
                ]);

                throw new \InvalidArgumentException(
                    'Cannot complete swap: This would create a duplicate shift assignment. '
                        . 'One of the users already has another shift on the target date. '
                        . 'Please check for existing assignments and try again.'
                );
            }

            // Re-throw other database errors
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to execute shift swap', [
                'error' => $e->getMessage(),
                'data' => $data,
                'tenant_id' => tenant()->id,
            ]);

            throw $e;
        }
    }

    /**
     * Get swap requests for a user
     */
    public function getSwapRequestsForUser(int $userId, ?array $filters = []): Collection
    {
        $query = ShiftSwapRequest::forUser($userId)
            ->with(['requesterAssignment.shift', 'targetAssignment.shift', 'requester', 'targetUser', 'manager']);

        // Apply filters
        if (isset($filters['swapped']) && filter_var($filters['swapped'], FILTER_VALIDATE_BOOLEAN)) {
            $query->swapped();
        }

        return $query->recent()->get();
    }

    /**
     * Get all swap requests (for managers)
     */
    public function getAllSwapRequests(?int $storeId = null, ?int $limit = null): Collection
    {
        $query = ShiftSwapRequest::with(['requesterAssignment.shift.store', 'targetAssignment.shift.store', 'requester', 'targetUser', 'manager']);

        if ($storeId) {
            $query->whereHas('requesterAssignment', function ($q) use ($storeId) {
                $q->where('store_id', $storeId);
            });
        }

        if ($limit) {
            $query->limit($limit);
        }

        return $query->recent()->get();
    }

    /**
     * Get swap request by ID
     */
    public function getSwapRequestById(int $id): ?ShiftSwapRequest
    {
        return ShiftSwapRequest::with([
            'requesterAssignment.shift',
            'targetAssignment.shift',
            'requester',
            'targetUser',
            'manager'
        ])->find($id);
    }

    /**
     * Get swap statistics
     */
    public function getSwapStatistics(?int $storeId = null): array
    {
        $query = ShiftSwapRequest::query();

        if ($storeId) {
            $query->whereHas('requesterAssignment', function ($q) use ($storeId) {
                $q->where('store_id', $storeId);
            });
        }

        $baseQuery = clone $query;

        return [
            'total_swaps' => (clone $baseQuery)->count(),
            'swaps_this_month' => (clone $baseQuery)
                ->whereMonth('swapped_at', now()->month)
                ->whereYear('swapped_at', now()->year)
                ->count(),
            'swaps_this_week' => (clone $baseQuery)
                ->whereBetween('swapped_at', [now()->startOfWeek(), now()->endOfWeek()])
                ->count(),
        ];
    }

    // ========================================
    // CACHE MANAGEMENT
    // ========================================

    /**
     * Clear swap-related cache
     */
    protected function clearSwapCache(): void
    {
        Cache::tags(['tenant', tenant()->id, 'shift_swaps'])->flush();
        // Also clear assignment cache as swaps modify assignments
        Cache::tags(['tenant', tenant()->id, 'shift_assignments'])->flush();
    }
}
