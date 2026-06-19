<?php

namespace App\Http\Requests\Tenant\Expense;

use Illuminate\Foundation\Http\FormRequest;

class UploadReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-expenses');
    }

    public function rules(): array
    {
        return [
            'receipt' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:5120', // 5MB
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'receipt.required' => 'Receipt file is required.',
            'receipt.mimes' => 'Receipt must be a PDF, JPG, or PNG file.',
            'receipt.max' => 'Receipt file size cannot exceed 5MB.',
        ];
    }
}
