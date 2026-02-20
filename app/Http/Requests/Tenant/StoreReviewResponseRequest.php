<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'response_text' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'response_text.required' => 'Response text is required.',
            'response_text.min'      => 'Response must be at least 10 characters.',
            'response_text.max'      => 'Response may not exceed 1000 characters.',
        ];
    }
}
