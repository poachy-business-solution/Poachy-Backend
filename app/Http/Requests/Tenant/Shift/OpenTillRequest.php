<?php

namespace App\Http\Requests\Tenant\Shift;

use Illuminate\Foundation\Http\FormRequest;

class OpenTillRequest extends FormRequest
{
    /**
     * Any signed-in staff member may open their own till.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'store_id' => [
                'required',
                'integer',
                'exists:stores,id',
            ],
            'opening_cash' => [
                'required',
                'numeric',
                'min:0',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'store_id.required' => 'Choose the store you are selling from.',
            'store_id.exists' => 'Selected store does not exist.',
            'opening_cash.required' => 'Count the cash in the drawer and enter the float.',
            'opening_cash.numeric' => 'Opening cash must be a valid number.',
            'opening_cash.min' => 'Opening cash cannot be negative.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
        ];
    }
}
