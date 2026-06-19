<?php

namespace App\Http\Requests\Central\Marketplace;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWishlistRequest extends FormRequest
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
            'notes' => ['nullable', 'string', 'max:1000'],
            'desired_quantity' => ['nullable', 'integer', 'min:1', 'max:9999'],
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'desired_quantity.min' => 'Desired quantity must be at least 1.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
        ];
    }
}
