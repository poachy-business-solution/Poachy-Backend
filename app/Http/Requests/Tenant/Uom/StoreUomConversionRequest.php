<?php

namespace App\Http\Requests\Tenant\Uom;

use Illuminate\Foundation\Http\FormRequest;

class StoreUomConversionRequest extends FormRequest
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
            'from_uom_id' => [
                'required',
                'integer',
                'exists:units_of_measure,id',
                'different:to_uom_id',
            ],
            'to_uom_id' => [
                'required',
                'integer',
                'exists:units_of_measure,id',
                'different:from_uom_id',
            ],
            'conversion_factor' => [
                'required',
                'numeric',
                'gt:0',
                'regex:/^\d+(\.\d{1,6})?$/', // Max 6 decimal places
            ],
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
            'from_uom_id.required' => 'Source unit is required.',
            'from_uom_id.exists' => 'Selected source unit does not exist.',
            'from_uom_id.different' => 'Source and target units must be different.',
            'to_uom_id.required' => 'Target unit is required.',
            'to_uom_id.exists' => 'Selected target unit does not exist.',
            'to_uom_id.different' => 'Source and target units must be different.',
            'conversion_factor.required' => 'Conversion factor is required.',
            'conversion_factor.gt' => 'Conversion factor must be greater than zero.',
            'conversion_factor.regex' => 'Conversion factor can have maximum 6 decimal places.',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Check if conversion already exists
            $exists = \App\Models\Tenant\UomConversion::where(function ($query) {
                $query->where('from_uom_id', $this->from_uom_id)
                    ->where('to_uom_id', $this->to_uom_id);
            })->orWhere(function ($query) {
                $query->where('from_uom_id', $this->to_uom_id)
                    ->where('to_uom_id', $this->from_uom_id);
            })->exists();

            if ($exists) {
                $validator->errors()->add(
                    'conversion',
                    'A conversion between these units already exists.'
                );
            }
        });
    }
}
