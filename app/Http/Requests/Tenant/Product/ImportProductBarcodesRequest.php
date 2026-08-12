<?php

namespace App\Http\Requests\Tenant\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportProductBarcodesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-products') ?? false;
    }

    public function rules(): array
    {
        return [
            'rows' => ['required', 'array', 'min:1', 'max:500'],
            'rows.*.target_type' => ['required', Rule::in(['product', 'variant', 'product_uom'])],
            'rows.*.product_uuid' => ['required_if:rows.*.target_type,product', 'required_if:rows.*.target_type,product_uom', 'string'],
            'rows.*.variant_id' => ['required_if:rows.*.target_type,variant', 'integer', 'exists:product_variants,id'],
            'rows.*.product_uom_id' => ['required_if:rows.*.target_type,product_uom', 'integer', 'exists:product_uoms,id'],
            'rows.*.barcode' => ['required', 'string', 'max:50'],
            'rows.*.barcode_type' => ['nullable', Rule::in([
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
            'rows.*.is_primary' => ['nullable', 'boolean'],
            'rows.*.is_active' => ['nullable', 'boolean'],
            'rows.*.supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'rows.*.region' => ['nullable', 'string', 'size:2'],
            'rows.*.store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'rows.*.valid_from' => ['nullable', 'date'],
            'rows.*.valid_until' => ['nullable', 'date', 'after_or_equal:rows.*.valid_from'],
            'rows.*.metadata' => ['nullable', 'array'],
            'rows.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $rows = collect($this->input('rows', []))
            ->map(function (array $row) {
                if (array_key_exists('barcode', $row)) {
                    $row['barcode'] = trim((string) $row['barcode']);
                }

                if (array_key_exists('region', $row)) {
                    $row['region'] = strtoupper((string) $row['region']);
                }

                return $row;
            })
            ->all();

        $this->merge(['rows' => $rows]);
    }
}
