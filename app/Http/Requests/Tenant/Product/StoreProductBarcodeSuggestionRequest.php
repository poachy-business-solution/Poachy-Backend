<?php

namespace App\Http\Requests\Tenant\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductBarcodeSuggestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'target_type' => ['required', Rule::in(['product', 'variant', 'product_uom'])],
            'product_uuid' => ['required_if:target_type,product', 'required_if:target_type,product_uom', 'string'],
            'variant_id' => ['required_if:target_type,variant', 'integer', 'exists:product_variants,id'],
            'product_uom_id' => ['required_if:target_type,product_uom', 'integer', 'exists:product_uoms,id'],
            'barcode' => ['required', 'string', 'max:50'],
            'barcode_type' => ['nullable', Rule::in($this->barcodeTypes())],
            'is_primary' => ['nullable', 'boolean'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'region' => ['nullable', 'string', 'size:2'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
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

    /**
     * @return array<int, string>
     */
    private function barcodeTypes(): array
    {
        return [
            'EAN-13',
            'EAN-8',
            'UPC-A',
            'UPC-E',
            'CODE-128',
            'CODE-39',
            'QR',
            'INTERNAL',
            'SCALE',
        ];
    }
}
