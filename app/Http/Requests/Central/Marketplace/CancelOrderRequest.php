<?php

namespace App\Http\Requests\Central\Marketplace;

use Illuminate\Foundation\Http\FormRequest;

class CancelOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('central')->check();
    }

    public function rules(): array
    {
        return [
            'cancellation_reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'cancellation_reason.required' => 'A reason is required when cancelling an order.',
            'cancellation_reason.max'      => 'Cancellation reason must not exceed 500 characters.',
        ];
    }
}
