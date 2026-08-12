<?php

namespace App\Http\Requests\Tenant\Product;

use Illuminate\Foundation\Http\FormRequest;

class LookupProductBarcodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'barcode' => ['required', 'string', 'max:50'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('barcode')) {
            $this->merge(['barcode' => trim((string) $this->input('barcode'))]);
        }
    }
}
