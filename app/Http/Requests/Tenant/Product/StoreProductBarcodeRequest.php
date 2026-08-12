<?php

namespace App\Http\Requests\Tenant\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductBarcodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-products') ?? false;
    }

    public function rules(): array
    {
        return [
            'barcode' => ['required', 'string', 'max:50'],
            'barcode_type' => ['nullable', Rule::in([
                'EAN-13',
                'EAN-8',
                'UPC-A',
                'UPC-E',
                'CODE-128',
                'CODE-39',
                'QR',
                'INTERNAL',
                'SCALE',
            ])],
            'is_primary' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'region' => ['nullable', 'string', 'size:2'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'source' => ['nullable', Rule::in([
                'manufacturer',
                'manual',
                'generated',
                'supplier',
                'imported',
                'scale',
            ])],
            'metadata' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('barcode')) {
            $this->merge(['barcode' => trim((string) $this->input('barcode'))]);
        }

        if ($this->has('region')) {
            $this->merge(['region' => strtoupper((string) $this->input('region'))]);
        }
    }
}
