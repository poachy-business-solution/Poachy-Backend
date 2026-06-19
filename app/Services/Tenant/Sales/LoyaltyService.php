<?php

namespace App\Services\Tenant\Sales;

use App\Enums\Tenant\LoyaltyTransactionType;
use App\Events\Tenant\LoyaltyPointsEarned;
use App\Events\Tenant\LoyaltyPointsRedeemed;
use App\Models\Tenant\Customer;
use App\Models\Tenant\LoyaltyTransaction;
use App\Models\Tenant\TenantConfiguration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LoyaltyService
{
    /**
     * Check if loyalty is enabled for this tenant
     */
    public function isEnabled(): bool
    {
        return TenantConfiguration::isEnabled('loyalty_enabled');
    }

    /**
     * Calculate loyalty points earned from a purchase
     */
    public function calculatePointsEarned(float $purchaseAmount, ?int $customerId = null): float
    {
        if (!$this->isEnabled() || !$customerId) {
            return 0;
        }

        $earningRate = TenantConfiguration::get('loyalty_earning_rate', 0.01);

        return round($purchaseAmount * $earningRate, 2);
    }

    /**
     * Calculate redemption value from points
     */
    public function calculateRedemptionValue(float $points): float
    {
        if (!$this->isEnabled()) {
            return 0;
        }

        $redemptionRate = TenantConfiguration::get('loyalty_redemption_rate', 1.0);

        return round($points * $redemptionRate, 2);
    }

    /**
     * Validate customer can redeem points
     */
    public function validateRedemption(Customer $customer, float $pointsToRedeem): array
    {
        if (!$this->isEnabled()) {
            return [
                'valid' => false,
                'message' => 'Loyalty program is not enabled',
            ];
        }

        $minRedemption = TenantConfiguration::get('loyalty_min_redemption_points', 100);

        if ($pointsToRedeem < $minRedemption) {
            return [
                'valid' => false,
                'message' => "Minimum {$minRedemption} points required for redemption",
            ];
        }

        if ($customer->loyalty_points < $pointsToRedeem) {
            return [
                'valid' => false,
                'message' => "Insufficient points. Available: {$customer->loyalty_points}",
            ];
        }

        return [
            'valid' => true,
            'message' => 'Redemption valid',
            'redemption_value' => $this->calculateRedemptionValue($pointsToRedeem),
        ];
    }

    /**
     * Award loyalty points for a transaction
     *
     * @param Customer $customer
     * @param float $points
     * @param string $referenceType
     * @param int $referenceId
     * @param string|null $description
     * @return LoyaltyTransaction
     */
    public function awardPoints(
        Customer $customer,
        float $points,
        ?string $referenceType = null,
        int $referenceId,
        ?string $description = null
    ): LoyaltyTransaction {
        if (!$this->isEnabled()) {
            throw new \RuntimeException('Loyalty program is not enabled');
        }

        return DB::transaction(function () use ($customer, $points, $referenceType, $referenceId, $description) {
            // Calculate expiry date
            $expiryDays = (int) TenantConfiguration::get('loyalty_points_expiry_days', 365);
            $expiryDate = now()->addDays($expiryDays);

            // Create transaction
            $transaction = LoyaltyTransaction::create([
                'customer_id' => $customer->id,
                'transaction_type' => LoyaltyTransactionType::EARNED,
                'points' => $points,
                'balance_after' => $customer->loyalty_points + $points,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $description ?? "Points earned",
                'expires_at' => $expiryDate,
            ]);

            // Update customer balance
            $customer->increment('loyalty_points', $points);

            // Dispatch event
            event(new LoyaltyPointsEarned($customer, $transaction));

            Log::info('Loyalty points awarded', [
                'tenant_id' => tenant()->id,
                'customer_id' => $customer->id,
                'points' => $points,
                'balance_after' => $transaction->balance_after,
            ]);

            return $transaction;
        });
    }

    /**
     * Redeem loyalty points
     *
     * @param Customer $customer
     * @param float $points
     * @param string $referenceType
     * @param int $referenceId
     * @param string|null $description
     * @return LoyaltyTransaction
     */
    public function redeemPoints(
        Customer $customer,
        float $points,
        string $referenceType,
        int $referenceId,
        ?string $description = null
    ): LoyaltyTransaction {
        if (!$this->isEnabled()) {
            throw new \RuntimeException('Loyalty program is not enabled');
        }

        // Validate redemption
        $validation = $this->validateRedemption($customer, $points);
        if (!$validation['valid']) {
            throw new \RuntimeException($validation['message']);
        }

        return DB::transaction(function () use ($customer, $points, $referenceType, $referenceId, $description) {
            // Create transaction
            $transaction = LoyaltyTransaction::create([
                'customer_id' => $customer->id,
                'transaction_type' => LoyaltyTransactionType::REDEEMED,
                'points' => -$points,
                'balance_after' => $customer->loyalty_points - $points,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $description ?? "Points redeemed",
            ]);

            // Update customer balance
            $customer->decrement('loyalty_points', $points);

            // Dispatch event
            event(new LoyaltyPointsRedeemed($customer, $transaction));

            Log::info('Loyalty points redeemed', [
                'tenant_id' => tenant()->id,
                'customer_id' => $customer->id,
                'points' => $points,
                'balance_after' => $transaction->balance_after,
            ]);

            return $transaction;
        });
    }

    /**
     * Process loyalty for a sale (both earning and redemption)
     */
    public function processSaleLoyalty(
        Customer $customer,
        float $saleAmount,
        float $pointsToRedeem,
        string $referenceType,
        int $referenceId,
        string $saleNumber
    ): array {
        if (!$this->isEnabled()) {
            return [
                'points_redeemed' => 0,
                'points_earned' => 0,
                'redemption_value' => 0,
                'transactions' => [],
            ];
        }

        $transactions = [];

        // Step 1: Redeem points (if any)
        $redemptionValue = 0;
        if ($pointsToRedeem > 0) {
            $redemptionTransaction = $this->redeemPoints(
                $customer,
                $pointsToRedeem,
                $referenceType,
                $referenceId,
                "Points redeemed for sale {$saleNumber}"
            );
            $transactions[] = $redemptionTransaction;
            $redemptionValue = $this->calculateRedemptionValue($pointsToRedeem);
        }

        // Step 2: Calculate and award points earned
        $pointsEarned = $this->calculatePointsEarned($saleAmount, $customer->id);
        if ($pointsEarned > 0) {
            $earningTransaction = $this->awardPoints(
                $customer,
                $pointsEarned,
                $referenceType,
                $referenceId,
                "Points earned from sale {$saleNumber}"
            );
            $transactions[] = $earningTransaction;
        }

        return [
            'points_redeemed' => $pointsToRedeem,
            'points_earned' => $pointsEarned,
            'redemption_value' => $redemptionValue,
            'transactions' => $transactions,
        ];
    }

    /**
     * Expire old loyalty points (scheduled job)
     */
    public function expireOldPoints(): int
    {
        if (!$this->isEnabled()) {
            return 0;
        }

        $expiredCount = 0;

        // Get all earned points that have expired and haven't been marked as expired
        $expiredTransactions = LoyaltyTransaction::where('transaction_type', LoyaltyTransactionType::EARNED)
            ->where('expires_at', '<', now())
            ->whereDoesntHave('customer.loyaltyTransactions', function ($query) {
                $query->where('transaction_type', LoyaltyTransactionType::EXPIRED);
            })
            ->with('customer')
            ->get();

        foreach ($expiredTransactions as $earnedTransaction) {
            DB::transaction(function () use ($earnedTransaction, &$expiredCount) {
                $customer = $earnedTransaction->customer;
                $pointsToExpire = $earnedTransaction->points;

                // Create expiry transaction
                LoyaltyTransaction::create([
                    'customer_id' => $customer->id,
                    'transaction_type' => LoyaltyTransactionType::EXPIRED,
                    'points' => -$pointsToExpire,
                    'balance_after' => $customer->loyalty_points - $pointsToExpire,
                    'reference_type' => LoyaltyTransaction::class,
                    'reference_id' => $earnedTransaction->id,
                    'description' => "Points expired from transaction #{$earnedTransaction->id}",
                ]);

                // Update customer balance
                $customer->decrement('loyalty_points', $pointsToExpire);

                $expiredCount++;
            });
        }

        Log::info('Loyalty points expired', [
            'tenant_id' => tenant()->id,
            'expired_count' => $expiredCount,
        ]);

        return $expiredCount;
    }

    /**
     * Get customer's expiring points (within X days)
     */
    public function getExpiringPoints(Customer $customer, int $days = 30): float
    {
        if (!$this->isEnabled()) {
            return 0;
        }

        $thresholdDate = now()->addDays($days);

        return LoyaltyTransaction::where('customer_id', $customer->id)
            ->where('transaction_type', LoyaltyTransactionType::EARNED)
            ->whereBetween('expires_at', [now(), $thresholdDate])
            ->where('points', '>', 0)
            ->sum('points');
    }

    /**
     * Calculate summary statistics for transactions list
     */
    public function calculateSummary(array $filters): array
    {
        $query = LoyaltyTransaction::query();

        // Apply same filters as main query
        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $earned = (clone $query)->earned()->sum('points');
        $redeemed = abs((clone $query)->redeemed()->sum('points'));
        $expired = abs((clone $query)->expired()->sum('points'));

        $uniqueCustomers = (clone $query)->distinct('customer_id')->count('customer_id');

        $redemptionRate = $earned > 0 ? ($redeemed / $earned) * 100 : 0;

        $pointsExpiringSoon = LoyaltyTransaction::expiringSoon(30)
            ->earned()
            ->sum('points');

        return [
            'total_points_earned' => round($earned, 2),
            'total_points_redeemed' => round($redeemed, 2),
            'total_points_expired' => round($expired, 2),
            'net_points_outstanding' => round($earned - $redeemed - $expired, 2),
            'unique_customers' => $uniqueCustomers,
            'avg_points_per_customer' => $uniqueCustomers > 0 ? round(($earned - $redeemed - $expired) / $uniqueCustomers, 2) : 0,
            'redemption_rate' => round($redemptionRate, 2),
            'points_expiring_soon' => round($pointsExpiringSoon, 2),
        ];
    }

    /**
     * Calculate customer balance summary
     */
    public function calculateCustomerBalanceSummary(int $customerId): array
    {
        $earned = LoyaltyTransaction::byCustomer($customerId)
            ->earned()
            ->sum('points');

        $redeemed = abs(LoyaltyTransaction::byCustomer($customerId)
            ->redeemed()
            ->sum('points'));

        $expired = abs(LoyaltyTransaction::byCustomer($customerId)
            ->expired()
            ->sum('points'));

        $expiringSoon = LoyaltyTransaction::byCustomer($customerId)
            ->expiringSoon(30)
            ->earned()
            ->sum('points');

        return [
            'total_earned' => round($earned, 2),
            'total_redeemed' => round($redeemed, 2),
            'total_expired' => round($expired, 2),
            'current_balance' => round($earned - $redeemed - $expired, 2),
            'points_expiring_soon' => round($expiringSoon, 2),
        ];
    }

    /**
     * Get overall loyalty program metrics
     */
    public function getOverallMetrics(): array
    {
        $totalMembers = Customer::where('loyalty_points', '>', 0)
            ->orWhereHas('loyaltyTransactions')
            ->count();

        $activeMembers = Customer::where('loyalty_points', '>', 0)->count();

        $issued = LoyaltyTransaction::earned()->sum('points');
        $redeemed = abs(LoyaltyTransaction::redeemed()->sum('points'));
        $expired = abs(LoyaltyTransaction::expired()->sum('points'));
        $outstanding = Customer::sum('loyalty_points');

        $redemptionRate = $issued > 0 ? ($redeemed / $issued) * 100 : 0;
        $avgPerMember = $activeMembers > 0 ? $outstanding / $activeMembers : 0;

        return [
            'total_members' => $totalMembers,
            'active_members' => $activeMembers,
            'total_points_issued' => round($issued, 2),
            'total_points_redeemed' => round($redeemed, 2),
            'total_points_expired' => round($expired, 2),
            'outstanding_points' => round($outstanding, 2),
            'redemption_rate' => round($redemptionRate, 2),
            'avg_points_per_member' => round($avgPerMember, 2),
        ];
    }

    /**
     * Get period-specific metrics
     */
    public function getPeriodMetrics(?string $dateFrom, ?string $dateTo): array
    {
        $query = LoyaltyTransaction::query();

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $earned = (clone $query)->earned()->sum('points');
        $redeemed = abs((clone $query)->redeemed()->sum('points'));
        $expired = abs((clone $query)->expired()->sum('points'));

        // New members who earned points in period
        $newMembers = (clone $query)
            ->earned()
            ->whereHas('customer', function ($q) use ($dateFrom) {
                if ($dateFrom) {
                    $q->whereDate('created_at', '>=', $dateFrom);
                }
            })
            ->distinct('customer_id')
            ->count('customer_id');

        // Active redeemers in period
        $activeRedeemers = (clone $query)
            ->redeemed()
            ->distinct('customer_id')
            ->count('customer_id');

        return [
            'points_earned' => round($earned, 2),
            'points_redeemed' => round($redeemed, 2),
            'points_expired' => round($expired, 2),
            'new_members' => $newMembers,
            'active_redeemers' => $activeRedeemers,
        ];
    }

    /**
     * Get expiry analysis
     */
    public function getExpiryAnalysis(): array
    {
        return [
            'expiring_within_7_days' => round(
                LoyaltyTransaction::expiringSoon(7)->earned()->sum('points'),
                2
            ),
            'expiring_within_30_days' => round(
                LoyaltyTransaction::expiringSoon(30)->earned()->sum('points'),
                2
            ),
            'expiring_within_90_days' => round(
                LoyaltyTransaction::expiringSoon(90)->earned()->sum('points'),
                2
            ),
        ];
    }

    /**
     * Get top earners
     */
    public function getTopEarners(?string $dateFrom, ?string $dateTo, int $limit = 10): array
    {
        $query = LoyaltyTransaction::query()
            ->select('customer_id', DB::raw('SUM(points) as total_points'))
            ->earned()
            ->groupBy('customer_id')
            ->orderByDesc('total_points')
            ->limit($limit);

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        return $query->with('customer:id,name,phone')
            ->get()
            ->map(function ($item) {
                return [
                    'customer_id' => $item->customer_id,
                    'customer_name' => $item->customer->name ?? 'Unknown',
                    'points_earned' => round($item->total_points, 2),
                ];
            })
            ->toArray();
    }

    /**
     * Get top redeemers
     */
    public function getTopRedeemers(?string $dateFrom, ?string $dateTo, int $limit = 10): array
    {
        $query = LoyaltyTransaction::query()
            ->select('customer_id', DB::raw('ABS(SUM(points)) as total_points'))
            ->redeemed()
            ->groupBy('customer_id')
            ->orderByDesc('total_points')
            ->limit($limit);

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        return $query->with('customer:id,name,phone')
            ->get()
            ->map(function ($item) {
                return [
                    'customer_id' => $item->customer_id,
                    'customer_name' => $item->customer->name ?? 'Unknown',
                    'points_redeemed' => round($item->total_points, 2),
                ];
            })
            ->toArray();
    }
}
