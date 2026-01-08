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
        string $referenceType,
        int $referenceId,
        ?string $description = null
    ): LoyaltyTransaction {
        if (!$this->isEnabled()) {
            throw new \RuntimeException('Loyalty program is not enabled');
        }

        return DB::transaction(function () use ($customer, $points, $referenceType, $referenceId, $description) {
            // Calculate expiry date
            $expiryDays = TenantConfiguration::get('loyalty_points_expiry_days', 365);
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
}
