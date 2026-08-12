<?php

namespace App\Http\Requests\Tenant\Product;

use App\Models\Tenant\ProductScaleBarcodeFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProductScaleBarcodeFormatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-products') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'prefix' => ['sometimes', 'string', 'max:20', 'regex:/^[0-9]+$/'],
            'length' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'product_code_start' => ['sometimes', 'integer', 'min:0'],
            'product_code_length' => ['sometimes', 'integer', 'min:1'],
            'value_start' => ['sometimes', 'integer', 'min:0'],
            'value_length' => ['sometimes', 'integer', 'min:1'],
            'value_type' => ['sometimes', Rule::in(['weight', 'price', 'quantity'])],
            'decimal_places' => ['sometimes', 'integer', 'min:0', 'max:6'],
            'checksum' => ['nullable', Rule::in(['ean13'])],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'is_active' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'metadata' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->validateSegments($validator));
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('prefix')) {
            $this->merge(['prefix' => trim((string) $this->input('prefix'))]);
        }

        if ($this->has('checksum')) {
            $this->merge(['checksum' => $this->input('checksum') ? strtolower((string) $this->input('checksum')) : null]);
        }
    }

    protected function validateSegments(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        /** @var ProductScaleBarcodeFormat $format */
        $format = $this->route('scaleBarcodeFormat');
        $data = array_merge($format->only([
            'prefix',
            'length',
            'product_code_start',
            'product_code_length',
            'value_start',
            'value_length',
            'checksum',
        ]), $validator->getData());

        $length = (int) $data['length'];
        $productStart = (int) $data['product_code_start'];
        $productLength = (int) $data['product_code_length'];
        $valueStart = (int) $data['value_start'];
        $valueLength = (int) $data['value_length'];

        if (strlen((string) $data['prefix']) > $length) {
            $validator->errors()->add('prefix', 'The prefix must fit within the barcode length.');
        }

        if ($productStart + $productLength > $length) {
            $validator->errors()->add('product_code_length', 'The product code segment must fit within the barcode length.');
        }

        if ($valueStart + $valueLength > $length) {
            $validator->errors()->add('value_length', 'The value segment must fit within the barcode length.');
        }

        if ($productStart < $valueStart + $valueLength && $valueStart < $productStart + $productLength) {
            $validator->errors()->add('value_start', 'The value segment must not overlap the product code segment.');
        }

        if (($data['checksum'] ?? null) === 'ean13' && $length !== 13) {
            $validator->errors()->add('checksum', 'The ean13 checksum requires a barcode length of 13.');
        }
    }
}
