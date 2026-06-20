<?php

namespace App\Http\Requests\Tenant\Subscription;

use Illuminate\Foundation\Http\FormRequest;

class InitiateSubscriptionPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'integer', 'exists:central.subscription_plans,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'plan_id.required' => 'A subscription plan is required.',
            'plan_id.exists'   => 'The selected subscription plan does not exist.',
        ];
    }
}
