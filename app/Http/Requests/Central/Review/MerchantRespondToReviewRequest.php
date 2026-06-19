<?php

namespace App\Http\Requests\Central\Review;

use Illuminate\Foundation\Http\FormRequest;

class MerchantRespondToReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id'     => ['required', 'string', 'exists:central.tenants,id'],
            'response_text' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'tenant_id.required'     => 'A tenant identifier is required.',
            'tenant_id.exists'       => 'The specified merchant could not be found.',
            'response_text.required' => 'Response text is required.',
            'response_text.min'      => 'Response must be at least 10 characters.',
            'response_text.max'      => 'Response may not exceed 1,000 characters.',
        ];
    }
}
