<?php

namespace App\Http\Requests\Tenant\Supplier;

use App\Enums\Tenant\PaymentMethod;
use App\Rules\Tenant\PurchaseOrderBelongsToSupplier;
use App\Rules\Tenant\RequiredForPaymentMethod;
use App\Rules\Tenant\SupplierIsActive;
use App\Rules\Tenant\ValidPaymentAmount;
use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierPaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by policy in controller
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => [
                'required',
                'integer',
                'exists:suppliers,id',
                new SupplierIsActive(),
            ],
            'purchase_order_id' => [
                'nullable',
                'integer',
                'exists:purchase_orders,id',
                new PurchaseOrderBelongsToSupplier($this->input('supplier_id')),
            ],
            'payment_date' => [
                'required',
                'date',
                'before_or_equal:today',
            ],
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
                new ValidPaymentAmount(
                    $this->input('supplier_id'),
                    $this->input('purchase_order_id')
                ),
            ],
            'payment_method' => [
                'required',
                'string',
                'in:' . implode(',', PaymentMethod::values()),
            ],
            'reference_number' => [
                'nullable',
                'string',
                'max:100',
                new RequiredForPaymentMethod($this->input('payment_method')),
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'receipt' => [
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:5120', // 5MB
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'supplier_id.required' => 'Supplier is required.',
            'supplier_id.exists' => 'Selected supplier does not exist.',
            'purchase_order_id.exists' => 'Selected purchase order does not exist.',
            'payment_date.required' => 'Payment date is required.',
            'payment_date.date' => 'Payment date must be a valid date.',
            'payment_date.before_or_equal' => 'Payment date cannot be in the future.',
            'amount.required' => 'Payment amount is required.',
            'amount.numeric' => 'Payment amount must be a number.',
            'amount.min' => 'Payment amount must be greater than zero.',
            'payment_method.required' => 'Payment method is required.',
            'payment_method.in' => 'Invalid payment method selected.',
            'reference_number.max' => 'Reference number cannot exceed 100 characters.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
            'receipt.file' => 'Receipt must be a file.',
            'receipt.mimes' => 'Receipt must be a PDF, JPG, JPEG, or PNG file.',
            'receipt.max' => 'Receipt file size cannot exceed 5MB.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'supplier_id' => 'supplier',
            'purchase_order_id' => 'purchase order',
            'payment_date' => 'payment date',
            'amount' => 'payment amount',
            'payment_method' => 'payment method',
            'reference_number' => 'reference number',
            'notes' => 'notes',
            'receipt' => 'receipt',
        ];
    }
}
