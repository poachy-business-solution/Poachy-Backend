<?php

namespace App\Services\Tenant\Sales;

use App\Enums\Tenant\CreditTransactionType;
use App\Enums\Tenant\PaymentMethod;
use App\Events\Tenant\CreditLimitExceeded;
use App\Events\Tenant\CreditPaymentReceived;
use App\Events\Tenant\CreditSaleCreated;
use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerCreditTransaction;
use App\Models\Tenant\TenantConfiguration;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreditService
{
    /**
     * Check if credit sales are enabled for this tenant
     */
    public function isEnabled(): bool
    {
        return TenantConfiguration::isEnabled('credit_enabled');
    }

    /**
     * Get default credit limit
     */
    public function getDefaultCreditLimit(): float
    {
        return (float) TenantConfiguration::get('credit_default_limit', 10000);
    }

    /**
     * Get grace period in days
     */
    public function getGracePeriodDays(): int
    {
        return (int) TenantConfiguration::get('credit_grace_period_days', 30);
    }

    /**
     * Validate credit sale is allowed
     */
    public function validateCreditSale(Customer $customer, float $saleAmount): array
    {
        if (!$this->isEnabled()) {
            return [
                'valid' => false,
                'message' => 'Credit sales are not enabled',
            ];
        }

        if (!$customer->is_active) {
            return [
                'valid' => false,
                'message' => 'Customer account is inactive',
            ];
        }

        $newDebt = $customer->current_debt + $saleAmount;

        if ($newDebt > $customer->credit_limit) {
            return [
                'valid' => false,
                'message' => "Credit limit exceeded. Limit: {$customer->credit_limit}, Current debt: {$customer->current_debt}, New sale: {$saleAmount}",
                'credit_limit' => $customer->credit_limit,
                'current_debt' => $customer->current_debt,
                'available_credit' => $customer->available_credit,
            ];
        }

        return [
            'valid' => true,
            'message' => 'Credit sale allowed',
            'new_balance' => $newDebt,
            'remaining_credit' => $customer->credit_limit - $newDebt,
        ];
    }

    /**
     * Create credit sale transaction
     *
     * @param Customer $customer
     * @param float $amount
     * @param string $referenceType
     * @param int $referenceId
     * @param string|null $notes
     * @return CustomerCreditTransaction
     */
    public function recordCreditSale(
        Customer $customer,
        float $amount,
        string $referenceType,
        int $referenceId,
        ?string $notes = null
    ): CustomerCreditTransaction {
        if (!$this->isEnabled()) {
            throw new \RuntimeException('Credit sales are not enabled');
        }

        // Validate credit sale
        $validation = $this->validateCreditSale($customer, $amount);
        if (!$validation['valid']) {
            event(new CreditLimitExceeded($customer, $amount));
            throw new \RuntimeException($validation['message']);
        }

        return DB::transaction(function () use ($customer, $amount, $referenceType, $referenceId, $notes) {
            // Create transaction
            $transaction = CustomerCreditTransaction::create([
                'customer_id' => $customer->id,
                'transaction_type' => CreditTransactionType::SALE_ON_CREDIT,
                'amount' => $amount,
                'balance_after' => $customer->current_debt + $amount,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'notes' => $notes,
                'created_by' => Auth::id(),
            ]);

            // Update customer debt
            $customer->increment('current_debt', $amount);

            // Dispatch event
            event(new CreditSaleCreated($customer, $transaction));

            Log::info('Credit sale recorded', [
                'tenant_id' => tenant()->id,
                'customer_id' => $customer->id,
                'amount' => $amount,
                'balance_after' => $transaction->balance_after,
            ]);

            return $transaction;
        });
    }

    /**
     * Record credit payment
     *
     * @param Customer $customer
     * @param float $amount
     * @param PaymentMethod $paymentMethod
     * @param string|null $paymentReference
     * @param string|null $referenceType
     * @param int|null $referenceId
     * @param string|null $notes
     * @return CustomerCreditTransaction
     */
    public function recordPayment(
        Customer $customer,
        float $amount,
        PaymentMethod $paymentMethod,
        ?string $paymentReference = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null
    ): CustomerCreditTransaction {
        if (!$this->isEnabled()) {
            throw new \RuntimeException('Credit sales are not enabled');
        }

        if ($amount <= 0) {
            throw new \RuntimeException('Payment amount must be greater than zero');
        }

        if ($amount > $customer->current_debt) {
            throw new \RuntimeException(
                "Payment amount ({$amount}) exceeds current debt ({$customer->current_debt})"
            );
        }

        return DB::transaction(function () use (
            $customer,
            $amount,
            $paymentMethod,
            $paymentReference,
            $referenceType,
            $referenceId,
            $notes
        ) {
            // Create transaction (negative amount = payment)
            $transaction = CustomerCreditTransaction::create([
                'customer_id' => $customer->id,
                'transaction_type' => CreditTransactionType::PAYMENT,
                'amount' => -$amount,
                'balance_after' => $customer->current_debt - $amount,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'payment_method' => $paymentMethod,
                'payment_reference' => $paymentReference,
                'notes' => $notes,
                'created_by' => Auth::id(),
            ]);

            // Update customer debt
            $customer->decrement('current_debt', $amount);

            // Dispatch event
            event(new CreditPaymentReceived($customer, $transaction));

            Log::info('Credit payment recorded', [
                'tenant_id' => tenant()->id,
                'customer_id' => $customer->id,
                'amount' => $amount,
                'payment_method' => $paymentMethod->value,
                'balance_after' => $transaction->balance_after,
            ]);

            return $transaction;
        });
    }

    /**
     * Record credit adjustment (manual adjustment by admin)
     */
    public function recordAdjustment(
        Customer $customer,
        float $amount,
        string $reason
    ): CustomerCreditTransaction {
        if (!$this->isEnabled()) {
            throw new \RuntimeException('Credit sales are not enabled');
        }

        return DB::transaction(function () use ($customer, $amount, $reason) {
            $transaction = CustomerCreditTransaction::create([
                'customer_id' => $customer->id,
                'transaction_type' => CreditTransactionType::ADJUSTMENT,
                'amount' => $amount,
                'balance_after' => $customer->current_debt + $amount,
                'notes' => "Manual adjustment: {$reason}",
                'created_by' => Auth::id(),
            ]);

            // Update customer debt
            if ($amount > 0) {
                $customer->increment('current_debt', $amount);
            } else {
                $customer->decrement('current_debt', abs($amount));
            }

            Log::warning('Credit adjustment recorded', [
                'tenant_id' => tenant()->id,
                'customer_id' => $customer->id,
                'amount' => $amount,
                'reason' => $reason,
                'adjusted_by' => Auth::id(),
            ]);

            return $transaction;
        });
    }

    /**
     * Write off bad debt
     */
    public function writeOff(
        Customer $customer,
        float $amount,
        string $reason
    ): CustomerCreditTransaction {
        if (!$this->isEnabled()) {
            throw new \RuntimeException('Credit sales are not enabled');
        }

        if ($amount > $customer->current_debt) {
            throw new \RuntimeException(
                "Write-off amount ({$amount}) exceeds current debt ({$customer->current_debt})"
            );
        }

        return DB::transaction(function () use ($customer, $amount, $reason) {
            $transaction = CustomerCreditTransaction::create([
                'customer_id' => $customer->id,
                'transaction_type' => CreditTransactionType::WRITE_OFF,
                'amount' => -$amount,
                'balance_after' => $customer->current_debt - $amount,
                'notes' => "Write-off: {$reason}",
                'created_by' => Auth::id(),
            ]);

            // Update customer debt
            $customer->decrement('current_debt', $amount);

            Log::warning('Debt written off', [
                'tenant_id' => tenant()->id,
                'customer_id' => $customer->id,
                'amount' => $amount,
                'reason' => $reason,
                'written_off_by' => Auth::id(),
            ]);

            return $transaction;
        });
    }

    /**
     * Get overdue customers
     */
    public function getOverdueCustomers(): \Illuminate\Support\Collection
    {
        if (!$this->isEnabled()) {
            return collect();
        }

        $gracePeriodDays = $this->getGracePeriodDays();
        $overdueDate = now()->subDays($gracePeriodDays);

        return Customer::where('current_debt', '>', 0)
            ->whereHas('creditTransactions', function ($query) use ($overdueDate) {
                $query->where('transaction_type', CreditTransactionType::SALE_ON_CREDIT)
                    ->where('created_at', '<', $overdueDate);
            })
            ->with(['creditTransactions' => function ($query) {
                $query->orderBy('created_at', 'desc');
            }])
            ->get();
    }

    /**
     * Get customer credit summary
     */
    public function getCreditSummary(Customer $customer): array
    {
        if (!$this->isEnabled()) {
            return [
                'enabled' => false,
            ];
        }

        $gracePeriodDays = $this->getGracePeriodDays();
        $overdueDate = now()->subDays($gracePeriodDays);

        $overdueAmount = CustomerCreditTransaction::where('customer_id', $customer->id)
            ->where('transaction_type', CreditTransactionType::SALE_ON_CREDIT)
            ->where('created_at', '<', $overdueDate)
            ->sum('amount');

        return [
            'enabled' => true,
            'credit_limit' => $customer->credit_limit,
            'current_debt' => $customer->current_debt,
            'available_credit' => $customer->available_credit,
            'overdue_amount' => max(0, $overdueAmount),
            'is_overdue' => $overdueAmount > 0,
            'grace_period_days' => $gracePeriodDays,
        ];
    }

    /**
     * Calculate summary statistics for transactions list
     */
    public function calculateSummary(array $filters): array
    {
        $query = CustomerCreditTransaction::query();

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

        $creditSales = (clone $query)->creditSales()->sum('amount');
        $payments = abs((clone $query)->payments()->sum('amount'));
        $writeOffs = abs((clone $query)->writeOffs()->sum('amount'));

        $outstanding = Customer::where('current_debt', '>', 0)->sum('current_debt');
        $uniqueCustomers = (clone $query)->distinct('customer_id')->count('customer_id');

        $collectionRate = $creditSales > 0 ? ($payments / $creditSales) * 100 : 0;
        $avgDebt = $uniqueCustomers > 0 ? $outstanding / $uniqueCustomers : 0;

        return [
            'total_credit_sales' => round($creditSales, 2),
            'total_payments' => round($payments, 2),
            'total_outstanding' => round($outstanding, 2),
            'total_write_offs' => round($writeOffs, 2),
            'unique_credit_customers' => $uniqueCustomers,
            'avg_debt_per_customer' => round($avgDebt, 2),
            'collection_rate' => round($collectionRate, 2),
        ];
    }

    /**
     * Calculate customer debt summary
     */
    public function calculateCustomerDebtSummary(int $customerId, Customer $customer): array
    {
        $creditSales = CustomerCreditTransaction::byCustomer($customerId)
            ->creditSales()
            ->sum('amount');

        $payments = abs(CustomerCreditTransaction::byCustomer($customerId)
            ->payments()
            ->sum('amount'));

        $adjustments = CustomerCreditTransaction::byCustomer($customerId)
            ->adjustments()
            ->sum('amount');

        $availableCredit = max(0, $customer->credit_limit - $customer->current_debt);
        $creditUtilization = $customer->credit_limit > 0
            ? ($customer->current_debt / $customer->credit_limit) * 100
            : 0;

        return [
            'total_credit_sales' => round($creditSales, 2),
            'total_payments' => round($payments, 2),
            'total_adjustments' => round($adjustments, 2),
            'current_debt' => round($customer->current_debt, 2),
            'credit_limit' => round($customer->credit_limit, 2),
            'available_credit' => round($availableCredit, 2),
            'credit_utilization' => round($creditUtilization, 2),
        ];
    }

    /**
     * Get overall credit program metrics
     */
    public function getOverallMetrics(): array
    {
        $totalCustomers = Customer::whereHas('creditTransactions')->count();
        $activeCustomers = Customer::where('current_debt', '>', 0)->count();

        $issued = CustomerCreditTransaction::creditSales()->sum('amount');
        $payments = abs(CustomerCreditTransaction::payments()->sum('amount'));
        $outstanding = Customer::sum('current_debt');
        $writeOffs = abs(CustomerCreditTransaction::writeOffs()->sum('amount'));

        $collectionRate = $issued > 0 ? ($payments / $issued) * 100 : 0;
        $avgDebt = $activeCustomers > 0 ? $outstanding / $activeCustomers : 0;

        return [
            'total_credit_customers' => $totalCustomers,
            'active_credit_customers' => $activeCustomers,
            'total_credit_issued' => round($issued, 2),
            'total_payments_received' => round($payments, 2),
            'total_outstanding_debt' => round($outstanding, 2),
            'total_write_offs' => round($writeOffs, 2),
            'collection_rate' => round($collectionRate, 2),
            'avg_debt_per_customer' => round($avgDebt, 2),
        ];
    }

    /**
     * Get period-specific metrics
     */
    public function getPeriodMetrics(?string $dateFrom, ?string $dateTo): array
    {
        $query = CustomerCreditTransaction::query();

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $creditSales = (clone $query)->creditSales()->sum('amount');
        $payments = abs((clone $query)->payments()->sum('amount'));
        $writeOffs = abs((clone $query)->writeOffs()->sum('amount'));

        // New credit customers in period
        $newCustomers = (clone $query)
            ->creditSales()
            ->whereHas('customer', function ($q) use ($dateFrom) {
                if ($dateFrom) {
                    $q->whereDate('created_at', '>=', $dateFrom);
                }
            })
            ->distinct('customer_id')
            ->count('customer_id');

        // Customers who made payments in period
        $customersWhoPaid = (clone $query)
            ->payments()
            ->distinct('customer_id')
            ->count('customer_id');

        return [
            'credit_sales' => round($creditSales, 2),
            'payments_received' => round($payments, 2),
            'write_offs' => round($writeOffs, 2),
            'new_credit_customers' => $newCustomers,
            'customers_who_paid' => $customersWhoPaid,
        ];
    }

    /**
     * Get risk analysis
     */
    public function getRiskAnalysis(): array
    {
        $customers = Customer::where('current_debt', '>', 0)
            ->select('id', 'current_debt', 'credit_limit')
            ->get();

        $highRisk = 0;
        $mediumRisk = 0;
        $lowRisk = 0;
        $overLimit = 0;

        foreach ($customers as $customer) {
            if ($customer->current_debt > $customer->credit_limit) {
                $overLimit++;
                $highRisk++;
            } else {
                $utilization = $customer->credit_limit > 0
                    ? ($customer->current_debt / $customer->credit_limit) * 100
                    : 0;

                if ($utilization >= 80) {
                    $highRisk++;
                } elseif ($utilization >= 50) {
                    $mediumRisk++;
                } else {
                    $lowRisk++;
                }
            }
        }

        return [
            'high_risk_customers' => $highRisk,
            'medium_risk_customers' => $mediumRisk,
            'low_risk_customers' => $lowRisk,
            'customers_over_limit' => $overLimit,
        ];
    }

    /**
     * Get top debtors
     */
    public function getTopDebtors(int $limit = 10): array
    {
        return Customer::where('current_debt', '>', 0)
            ->select('id', 'name', 'phone', 'current_debt', 'credit_limit')
            ->orderByDesc('current_debt')
            ->limit($limit)
            ->get()
            ->map(function ($customer) {
                $utilization = $customer->credit_limit > 0
                    ? ($customer->current_debt / $customer->credit_limit) * 100
                    : 0;

                return [
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->name,
                    'customer_phone' => $customer->phone,
                    'current_debt' => round($customer->current_debt, 2),
                    'credit_limit' => round($customer->credit_limit, 2),
                    'credit_utilization' => round($utilization, 2),
                ];
            })
            ->toArray();
    }

    /**
     * Get best payers
     */
    public function getBestPayers(?string $dateFrom, ?string $dateTo, int $limit = 10): array
    {
        $query = CustomerCreditTransaction::query()
            ->select('customer_id', DB::raw('ABS(SUM(amount)) as total_paid'))
            ->payments()
            ->groupBy('customer_id')
            ->orderByDesc('total_paid')
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
                    'customer_phone' => $item->customer->phone ?? 'N/A',
                    'total_paid' => round($item->total_paid, 2),
                ];
            })
            ->toArray();
    }
}
