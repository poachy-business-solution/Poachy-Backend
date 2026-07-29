<?php

namespace Tests\Feature\Tenant\Events;

use App\Events\Tenant\BatchExpired;
use App\Events\Tenant\BudgetAlertTriggered;
use App\Events\Tenant\BudgetExceeded;
use App\Events\Tenant\ExpenseApproved;
use App\Events\Tenant\ExpenseCreatedPendingApproval;
use App\Events\Tenant\ExpenseRejected;
use App\Events\Tenant\StockTransferApproved;
use App\Events\Tenant\StockTransferCancelled;
use App\Events\Tenant\StockTransferCompleted;
use App\Events\Tenant\StockTransferCreatedPendingApproval;
use App\Events\Tenant\StockTransferInTransit;
use App\Models\Tenant\Budget;
use App\Models\Tenant\Expense;
use App\Models\Tenant\ProductBatch;
use App\Models\Tenant\StockTransfer;
use Tests\TestCase;

class EventClassConstructionTest extends TestCase
{
    public function test_expense_created_pending_approval_holds_the_expense(): void
    {
        $expense = new Expense;
        $event = new ExpenseCreatedPendingApproval($expense);

        $this->assertSame($expense, $event->expense);
    }

    public function test_expense_approved_holds_the_expense(): void
    {
        $expense = new Expense;
        $event = new ExpenseApproved($expense);

        $this->assertSame($expense, $event->expense);
    }

    public function test_expense_rejected_holds_the_expense(): void
    {
        $expense = new Expense;
        $event = new ExpenseRejected($expense);

        $this->assertSame($expense, $event->expense);
    }

    public function test_budget_alert_triggered_holds_the_budget(): void
    {
        $budget = new Budget;
        $event = new BudgetAlertTriggered($budget);

        $this->assertSame($budget, $event->budget);
    }

    public function test_budget_exceeded_holds_the_budget(): void
    {
        $budget = new Budget;
        $event = new BudgetExceeded($budget);

        $this->assertSame($budget, $event->budget);
    }

    public function test_stock_transfer_created_pending_approval_holds_the_transfer(): void
    {
        $transfer = new StockTransfer;
        $event = new StockTransferCreatedPendingApproval($transfer);

        $this->assertSame($transfer, $event->transfer);
    }

    public function test_stock_transfer_approved_holds_the_transfer(): void
    {
        $transfer = new StockTransfer;
        $event = new StockTransferApproved($transfer);

        $this->assertSame($transfer, $event->transfer);
    }

    public function test_stock_transfer_in_transit_holds_the_transfer(): void
    {
        $transfer = new StockTransfer;
        $event = new StockTransferInTransit($transfer);

        $this->assertSame($transfer, $event->transfer);
    }

    public function test_stock_transfer_completed_holds_the_transfer(): void
    {
        $transfer = new StockTransfer;
        $event = new StockTransferCompleted($transfer);

        $this->assertSame($transfer, $event->transfer);
    }

    public function test_stock_transfer_cancelled_holds_the_transfer(): void
    {
        $transfer = new StockTransfer;
        $event = new StockTransferCancelled($transfer);

        $this->assertSame($transfer, $event->transfer);
    }

    public function test_batch_expired_holds_the_batch(): void
    {
        $batch = new ProductBatch;
        $event = new BatchExpired($batch);

        $this->assertSame($batch, $event->batch);
    }
}
