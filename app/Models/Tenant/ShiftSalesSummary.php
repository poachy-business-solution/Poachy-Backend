<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShiftSalesSummary extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'shift_sales_summary';

    protected $fillable = [
        'shift_assignment_id',
        'total_transactions',
        'total_sales_amount',
        'total_cash_sales',
        'total_card_sales',
        'total_mpesa_sales',
        'total_credit_sales',
        'total_refunds',
        'total_refund_amount',
        'total_discounts_given',
        'unique_customers',
    ];

    protected $casts = [
        'total_transactions' => 'integer',
        'total_sales_amount' => 'decimal:2',
        'total_cash_sales' => 'decimal:2',
        'total_card_sales' => 'decimal:2',
        'total_mpesa_sales' => 'decimal:2',
        'total_credit_sales' => 'decimal:2',
        'total_refunds' => 'integer',
        'total_refund_amount' => 'decimal:2',
        'total_discounts_given' => 'decimal:2',
        'unique_customers' => 'integer',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================

    public function shiftAssignment(): BelongsTo
    {
        return $this->belongsTo(ShiftAssignment::class);
    }

    // ========================================
    // ACCESSORS
    // ========================================

    public function getNetSalesAttribute(): float
    {
        return $this->total_sales_amount - $this->total_refund_amount;
    }

    /**
     * Get expected cash in register
     * Expected = opening_cash + cash_sales - cash_refunds
     */
    public function getExpectedCashAttribute(): float
    {
        $assignment = $this->shiftAssignment;

        if (!$assignment || $assignment->opening_cash === null) {
            return $this->total_cash_sales;
        }

        // This will be enhanced when sales module includes cash refunds tracking
        return $assignment->opening_cash + $this->total_cash_sales;
    }

    /**
     * Get cash variance vs expected
     */
    public function getCashVarianceVsExpectedAttribute(): ?float
    {
        $assignment = $this->shiftAssignment;

        if (!$assignment || $assignment->closing_cash === null) {
            return null;
        }

        return $assignment->closing_cash - $this->expected_cash;
    }

    /**
     * Get average transaction value
     */
    public function getAverageTransactionValueAttribute(): float
    {
        if ($this->total_transactions === 0) {
            return 0;
        }

        return round($this->total_sales_amount / $this->total_transactions, 2);
    }

    /**
     * Get refund rate (percentage)
     */
    public function getRefundRateAttribute(): float
    {
        if ($this->total_transactions === 0) {
            return 0;
        }

        return round(($this->total_refunds / $this->total_transactions) * 100, 2);
    }

    /**
     * Get discount rate (percentage of sales)
     */
    public function getDiscountRateAttribute(): float
    {
        if ($this->total_sales_amount == 0) {
            return 0;
        }

        return round(($this->total_discounts_given / $this->total_sales_amount) * 100, 2);
    }

    // ========================================
    // HELPER METHODS
    // ========================================

    public function getSalesPerHour(): ?float
    {
        $assignment = $this->shiftAssignment;

        if (!$assignment || !$assignment->actual_duration_minutes) {
            return null;
        }

        $hours = $assignment->actual_duration_minutes / 60;

        if ($hours === 0) {
            return null;
        }

        return round($this->total_sales_amount / $hours, 2);
    }

    public function getPaymentMethodBreakdown(): array
    {
        $total = $this->total_sales_amount;

        if ($total == 0) {
            return [
                'cash' => 0,
                'card' => 0,
                'mpesa' => 0,
                'credit' => 0,
            ];
        }

        return [
            'cash' => round(($this->total_cash_sales / $total) * 100, 2),
            'card' => round(($this->total_card_sales / $total) * 100, 2),
            'mpesa' => round(($this->total_mpesa_sales / $total) * 100, 2),
            'credit' => round(($this->total_credit_sales / $total) * 100, 2),
        ];
    }
}
