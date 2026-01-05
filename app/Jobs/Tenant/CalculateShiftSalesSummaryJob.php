<?php

namespace App\Jobs\Tenant;

use App\Models\Tenant\ShiftAssignment;
use App\Models\Tenant\ShiftSalesSummary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CalculateShiftSalesSummaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;
    public $backoff = [30, 60, 120];

    protected string $tenantId;
    protected int $shiftAssignmentId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $tenantId, int $shiftAssignmentId)
    {
        $this->tenantId = $tenantId;
        $this->shiftAssignmentId = $shiftAssignmentId;

        // Set queue for this job
        $this->onQueue('sync-normal');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Initialize tenancy
        $tenant = \App\Models\Tenant::find($this->tenantId);

        if (!$tenant) {
            Log::error('Tenant not found for CalculateShiftSalesSummaryJob', [
                'tenant_id' => $this->tenantId,
            ]);
            return;
        }

        tenancy()->initialize($tenant);

        try {
            $assignment = ShiftAssignment::find($this->shiftAssignmentId);

            if (!$assignment) {
                Log::warning('Shift assignment not found for sales summary', [
                    'tenant_id' => $this->tenantId,
                    'assignment_id' => $this->shiftAssignmentId,
                ]);
                return;
            }

            // Check if assignment is completed
            if ($assignment->status->value !== 'completed') {
                Log::info('Shift assignment not completed yet, skipping sales summary', [
                    'tenant_id' => $this->tenantId,
                    'assignment_id' => $this->shiftAssignmentId,
                    'status' => $assignment->status->value,
                ]);
                return;
            }

            // Calculate sales summary
            $this->calculateSalesSummary($assignment);

            Log::info('Shift sales summary calculated', [
                'tenant_id' => $this->tenantId,
                'assignment_id' => $this->shiftAssignmentId,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to calculate shift sales summary', [
                'tenant_id' => $this->tenantId,
                'assignment_id' => $this->shiftAssignmentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        } finally {
            tenancy()->end();
        }
    }

    /**
     * Calculate and save sales summary
     */
    protected function calculateSalesSummary(ShiftAssignment $assignment): void
    {
        DB::beginTransaction();

        try {
            // TODO: This will integrate with the Sales module when implemented
            // For now, create a placeholder summary

            ShiftSalesSummary::updateOrCreate(
                ['shift_assignment_id' => $assignment->id],
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

            // Future implementation will query sales table:
            // 
            // $summary = DB::table('sales')
            //     ->where('served_by', $assignment->user_id)
            //     ->where('store_id', $assignment->store_id)
            //     ->whereBetween('sale_date', [$assignment->actual_start, $assignment->actual_end])
            //     ->selectRaw('
            //         COUNT(*) as total_transactions,
            //         SUM(total_amount) as total_sales_amount,
            //         SUM(CASE WHEN payment_method = "cash" THEN amount_paid ELSE 0 END) as total_cash_sales,
            //         SUM(CASE WHEN payment_method = "card" THEN amount_paid ELSE 0 END) as total_card_sales,
            //         SUM(CASE WHEN payment_method = "mpesa" THEN amount_paid ELSE 0 END) as total_mpesa_sales,
            //         SUM(CASE WHEN payment_method = "credit" THEN amount_paid ELSE 0 END) as total_credit_sales,
            //         SUM(discount_amount) as total_discounts_given,
            //         COUNT(DISTINCT customer_id) as unique_customers
            //     ')
            //     ->first();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('CalculateShiftSalesSummaryJob failed permanently', [
            'tenant_id' => $this->tenantId,
            'assignment_id' => $this->shiftAssignmentId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
