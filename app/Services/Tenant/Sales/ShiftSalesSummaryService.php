<?php

namespace App\Services\Tenant\Sales;

use App\Enums\Tenant\PaymentMethod;
use App\Models\Tenant\Sale;
use App\Models\Tenant\ShiftAssignment;
use App\Models\Tenant\ShiftSalesSummary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShiftSalesSummaryService
{
    /**
     * Get or create sales summary for a shift
     */
    public function getOrCreateForShift(int $shiftAssignmentId): ShiftSalesSummary
    {
        return ShiftSalesSummary::firstOrCreate(
            ['shift_assignment_id' => $shiftAssignmentId],
            [
                'total_transactions' => 0,
                'total_sales_amount' => 0,
                'total_cash_sales' => 0,
                'total_card_sales' => 0,
                'total_mpesa_sales' => 0,
                'total_credit_sales' => 0,
                'total_refunds' => 0,
                'total_refund_amount' => 0,
                'total_discounts_given' => 0,
                'unique_customers' => 0,
            ]
        );
    }

    /**
     * Update summary from a completed sale
     * Uses row-level locking to prevent race conditions
     */
    public function updateFromSale(Sale $sale): ShiftSalesSummary
    {
        if (!$sale->shift_assignment_id) {
            throw new \InvalidArgumentException('Sale is not linked to a shift assignment');
        }

        // Eager load payments to avoid N+1
        $sale->loadMissing('payments');

        return DB::transaction(function () use ($sale) {
            // Lock the summary row for update or create if not exists
            $summary = ShiftSalesSummary::where('shift_assignment_id', $sale->shift_assignment_id)
                ->lockForUpdate()
                ->first();

            if (!$summary) {
                // Create new summary with initial values
                $summary = ShiftSalesSummary::create([
                    'shift_assignment_id' => $sale->shift_assignment_id,
                    'total_transactions' => 0,
                    'total_sales_amount' => 0,
                    'total_cash_sales' => 0,
                    'total_card_sales' => 0,
                    'total_mpesa_sales' => 0,
                    'total_credit_sales' => 0,
                    'total_refunds' => 0,
                    'total_refund_amount' => 0,
                    'total_discounts_given' => 0,
                    'unique_customers' => 0,
                ]);
            }

            // Increment transaction count
            $summary->total_transactions += 1;

            // Add to total sales (revenue)
            $summary->total_sales_amount += $sale->total_amount;

            // Add to payment method totals using actual payment records
            // This handles split payments correctly
            foreach ($sale->payments as $payment) {
                $this->updatePaymentMethodTotal($summary, $payment->payment_method, $payment->amount);
            }

            // Add discounts given
            if ($sale->discount_amount > 0) {
                $summary->total_discounts_given += $sale->discount_amount;
            }

            // Update unique customers count
            if ($sale->customer_id) {
                $summary->unique_customers = Sale::where('shift_assignment_id', $sale->shift_assignment_id)
                    ->whereNotNull('customer_id')
                    ->distinct('customer_id')
                    ->count('customer_id');
            }

            $summary->save();

            Log::info('Shift sales summary updated', [
                'shift_assignment_id' => $sale->shift_assignment_id,
                'sale_id' => $sale->id,
                'total_transactions' => $summary->total_transactions,
                'total_sales_amount' => $summary->total_sales_amount,
            ]);

            return $summary;
        });
    }

    /**
     * Update payment method specific total
     */
    protected function updatePaymentMethodTotal(
        ShiftSalesSummary $summary,
        PaymentMethod $method,
        float $amount
    ): void {
        match ($method) {
            PaymentMethod::CASH => $summary->total_cash_sales += $amount,
            PaymentMethod::CARD => $summary->total_card_sales += $amount,
            PaymentMethod::MPESA => $summary->total_mpesa_sales += $amount,
            PaymentMethod::CREDIT => $summary->total_credit_sales += $amount,
            default => null,
        };
    }

    /**
     * Calculate expected cash in register
     * Uses actual cash payments, not revenue
     */
    public function calculateExpectedCash(ShiftAssignment $assignment): float
    {
        $openingCash = $assignment->opening_cash ?? 0;

        // Get actual cash received (from sale_payments table)
        // This handles overpayments and split payments correctly
        $cashReceived = \App\Models\Tenant\SalePayment::whereHas('sale', function ($query) use ($assignment) {
            $query->where('shift_assignment_id', $assignment->id);
        })
            ->where('payment_method', PaymentMethod::CASH)
            ->sum('amount');

        // Expected = opening + cash received - cash refunds (refunds not implemented yet)
        $expectedCash = $openingCash + $cashReceived;

        return round($expectedCash, 2);
    }

    /**
     * Calculate cash variance
     */
    public function getCashVariance(ShiftAssignment $assignment): ?float
    {
        if ($assignment->closing_cash === null) {
            return null;
        }

        $expectedCash = $this->calculateExpectedCash($assignment);
        $variance = $assignment->closing_cash - $expectedCash;

        return round($variance, 2);
    }

    /**
     * Check if cash variance is significant
     */
    public function hasSignificantCashVariance(ShiftAssignment $assignment, float $threshold = 100): bool
    {
        $variance = $this->getCashVariance($assignment);

        if ($variance === null) {
            return false;
        }

        return abs($variance) >= $threshold;
    }

    /**
     * Get comprehensive shift metrics
     */
    public function getShiftMetrics(ShiftAssignment $assignment): array
    {
        $summary = $assignment->salesSummary;

        if (!$summary) {
            return [
                'total_transactions' => 0,
                'total_sales_amount' => 0,
                'total_cash_sales' => 0,
                'total_card_sales' => 0,
                'total_mpesa_sales' => 0,
                'total_credit_sales' => 0,
                'total_discounts_given' => 0,
                'unique_customers' => 0,
                'average_transaction_value' => 0,
                'opening_cash' => $assignment->opening_cash ?? 0,
                'expected_cash' => $assignment->opening_cash ?? 0,
                'closing_cash' => $assignment->closing_cash,
                'cash_variance' => null,
                'has_significant_variance' => false,
                'sales_per_hour' => null,
                'payment_method_breakdown' => [
                    'cash' => 0,
                    'card' => 0,
                    'mpesa' => 0,
                    'credit' => 0,
                ],
            ];
        }

        $expectedCash = $this->calculateExpectedCash($assignment);
        $cashVariance = $this->getCashVariance($assignment);

        return [
            'total_transactions' => $summary->total_transactions,
            'total_sales_amount' => (float) $summary->total_sales_amount,
            'total_cash_sales' => (float) $summary->total_cash_sales,
            'total_card_sales' => (float) $summary->total_card_sales,
            'total_mpesa_sales' => (float) $summary->total_mpesa_sales,
            'total_credit_sales' => (float) $summary->total_credit_sales,
            'total_discounts_given' => (float) $summary->total_discounts_given,
            'unique_customers' => $summary->unique_customers,
            'average_transaction_value' => $summary->average_transaction_value,
            'opening_cash' => (float) ($assignment->opening_cash ?? 0),
            'expected_cash' => $expectedCash,
            'closing_cash' => $assignment->closing_cash ? (float) $assignment->closing_cash : null,
            'cash_variance' => $cashVariance,
            'has_significant_variance' => $this->hasSignificantCashVariance($assignment),
            'sales_per_hour' => $summary->getSalesPerHour(),
            'payment_method_breakdown' => $summary->getPaymentMethodBreakdown(),
        ];
    }

    /**
     * Recalculate summary from scratch (for corrections)
     */
    public function recalculateSummary(int $shiftAssignmentId): ShiftSalesSummary
    {
        return DB::transaction(function () use ($shiftAssignmentId) {
            $sales = Sale::forShift($shiftAssignmentId)
                ->with(['customer', 'payments'])
                ->get();

            $summary = $this->getOrCreateForShift($shiftAssignmentId);

            // Reset all counters
            $summary->total_transactions = $sales->count();
            $summary->total_sales_amount = $sales->sum('total_amount');
            $summary->total_discounts_given = $sales->sum('discount_amount');

            // Calculate payment method totals from sale_payments table
            // This correctly handles split payments
            $paymentTotals = \App\Models\Tenant\SalePayment::whereIn('sale_id', $sales->pluck('id'))
                ->select('payment_method', DB::raw('SUM(amount) as total'))
                ->groupBy('payment_method')
                ->get()
                ->keyBy('payment_method');

            $summary->total_cash_sales = $paymentTotals->get(PaymentMethod::CASH->value)?->total ?? 0;
            $summary->total_card_sales = $paymentTotals->get(PaymentMethod::CARD->value)?->total ?? 0;
            $summary->total_mpesa_sales = $paymentTotals->get(PaymentMethod::MPESA->value)?->total ?? 0;
            $summary->total_credit_sales = $paymentTotals->get(PaymentMethod::CREDIT->value)?->total ?? 0;

            $summary->unique_customers = $sales->whereNotNull('customer_id')
                ->pluck('customer_id')
                ->unique()
                ->count();

            // Refunds not implemented yet
            $summary->total_refunds = 0;
            $summary->total_refund_amount = 0;

            $summary->save();

            Log::info('Shift sales summary recalculated', [
                'shift_assignment_id' => $shiftAssignmentId,
                'total_transactions' => $summary->total_transactions,
                'total_sales_amount' => $summary->total_sales_amount,
            ]);

            return $summary;
        });
    }
}
