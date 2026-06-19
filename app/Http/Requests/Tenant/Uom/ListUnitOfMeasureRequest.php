<?php

namespace App\Http\Requests\Tenant\Uom;

use App\Enums\Tenant\UomSourceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListUnitOfMeasureRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['nullable', 'string', 'in:count,weight,volume,length,area,time'],
            'source_type' => ['nullable', 'string', Rule::in(UomSourceType::values())],
            'is_base_unit' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'type.in' => 'The type must be one of: count, weight, volume, length, area, time.',
            'source_type.in' => 'The source type must be either system or custom.',
            'per_page.max' => 'You cannot request more than 100 items per page.',
        ];
    }
}
