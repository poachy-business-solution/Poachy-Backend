<?php

namespace App\Http\Resources\Tenant\Expense;

use App\Http\Resources\Tenant\Auth\UserResource;
use App\Http\Resources\Tenant\Store\StoreResource;
use App\Http\Resources\Tenant\Supplier\SupplierResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'expense_number' => $this->expense_number,
            'store_id' => $this->store_id,
            'category_id' => $this->category_id,
            'amount' => $this->amount,
            'formatted_amount' => $this->formatted_amount,
            'description' => $this->description,
            'expense_date' => $this->expense_date?->toDateString(),

            // Payment
            'payment_method' => $this->payment_method?->value,
            'payment_method_label' => $this->payment_method_label,
            'payment_reference' => $this->payment_reference,
            'payment_status' => $this->payment_status?->value,
            'payment_status_label' => $this->payment_status_label,

            // Receipt
            'receipt_path' => $this->receipt_path,
            'receipt_url' => $this->getReceiptUrl(),
            'receipt_number' => $this->receipt_number,
            'has_receipt' => $this->has_receipt,

            // Recurrence
            'is_recurring' => $this->is_recurring,
            'recurrence_frequency' => $this->recurrence_frequency?->value,
            'recurrence_interval' => $this->recurrence_interval,
            'recurrence_start_date' => $this->recurrence_start_date?->toDateString(),
            'recurrence_end_date' => $this->recurrence_end_date?->toDateString(),
            'next_occurrence_date' => $this->next_occurrence_date?->toDateString(),
            'parent_expense_id' => $this->parent_expense_id,
            'is_recurrence_instance' => $this->is_recurrence_instance,

            // Supplier
            'supplier_id' => $this->supplier_id,

            // Approval
            'approval_status' => $this->approval_status?->value,
            'approval_status_label' => $this->approval_status_label,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->toISOString(),
            'rejection_reason' => $this->rejection_reason,

            // Computed
            'is_editable' => $this->is_editable,
            'is_deletable' => $this->is_deletable,
            'can_be_approved' => $this->can_be_approved,

            // Relationships
            'category' => $this->whenLoaded('category', function () {
                return new ExpenseCategoryResource($this->category);
            }),
            'store' => $this->whenLoaded('store', function () {
                return new StoreResource($this->store);
            }),
            'supplier' => $this->whenLoaded('supplier', function () {
                return new SupplierResource($this->supplier);
            }),
            'creator' => $this->whenLoaded('creator', function () {
                return new UserResource($this->creator);
            }),
            'approver' => $this->whenLoaded('approver', function () {
                return new UserResource($this->approver);
            }),
            'parent_expense' => $this->whenLoaded('parentExpense', function () {
                return new ExpenseResource($this->parentExpense);
            }),

            // Audit
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
