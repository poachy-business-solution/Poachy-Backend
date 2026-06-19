<?php

namespace App\Http\Requests\Tenant\Uom;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitOfMeasureRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization handled by policy
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $uomId = $this->route('unitOfMeasure');

        return [
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                'alpha_dash',
                Rule::unique('units_of_measure', 'code')
                    ->ignore($uomId)
                    ->where('tenant_id', tenant()->id ?? null),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', 'string', 'in:count,weight,volume,length,area,time,other'],
            'is_base_unit' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
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
            'code.unique' => 'This unit code already exists in your system.',
            'code.alpha_dash' => 'Unit code can only contain letters, numbers, dashes, and underscores.',
            'code.max' => 'Unit code cannot exceed 20 characters.',
            'type.in' => 'Unit type must be one of: count, weight, volume, length, area, time, other.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert code to lowercase for consistency
        if ($this->has('code')) {
            $this->merge([
                'code' => strtolower($this->code),
            ]);
        }
    }
}
