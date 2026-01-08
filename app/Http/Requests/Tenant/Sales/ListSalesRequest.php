<?php

namespace App\Http\Requests\Tenant\Sales;

use App\Enums\Tenant\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListSalesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'store_id' => [
                'nullable',
                'integer',
                'exists:stores,id',
            ],
            'customer_id' => [
                'nullable',
                'integer',
                'exists:customers,id',
            ],
            'payment_status' => [
                'nullable',
                'string',
                Rule::in(array_column(PaymentStatus::cases(), 'value')),
            ],
            'from_date' => [
                'nullable',
                'date',
                'date_format:Y-m-d',
            ],
            'to_date' => [
                'nullable',
                'date',
                'date_format:Y-m-d',
                'after_or_equal:from_date',
            ],
            'search' => [
                'nullable',
                'string',
                'max:100',
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
            'page' => [
                'nullable',
                'integer',
                'min:1',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'store_id.exists' => 'Selected store does not exist',
            'customer_id.exists' => 'Selected customer does not exist',
            'payment_status.in' => 'Invalid payment status. Must be one of: ' . implode(', ', array_column(PaymentStatus::cases(), 'value')),
            'from_date.date_format' => 'From date must be in format Y-m-d (e.g., 2025-01-08)',
            'to_date.date_format' => 'To date must be in format Y-m-d (e.g., 2025-01-08)',
            'to_date.after_or_equal' => 'To date must be after or equal to from date',
            'per_page.max' => 'Per page cannot exceed 100',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'from_date' => 'start date',
            'to_date' => 'end date',
            'per_page' => 'items per page',
        ];
    }
}
