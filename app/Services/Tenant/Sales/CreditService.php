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
}
