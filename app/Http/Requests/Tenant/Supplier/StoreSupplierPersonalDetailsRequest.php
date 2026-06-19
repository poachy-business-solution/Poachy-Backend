<?php

namespace App\Http\Requests\Tenant\Supplier;

use App\Enums\Tenant\SupplierType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierPersonalDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('suppliers', 'name')->whereNull('deleted_at'),],
            'supplier_type' => ['required', 'string', Rule::enum(SupplierType::class)],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('suppliers', 'email')->whereNull('deleted_at'),],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:1000'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Supplier name is required',
            'supplier_type.required' => 'Supplier type is required',
            'supplier_type.enum' => 'Invalid supplier type. Must be one of: ' .
                implode(', ', SupplierType::values()),
            'email.email' => 'Please provide a valid email address',
        ];
    }
}
