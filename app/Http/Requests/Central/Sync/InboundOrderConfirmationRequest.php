<?php

namespace App\Http\Requests\Central\Sync;

use Illuminate\Foundation\Http\FormRequest;

class InboundOrderConfirmationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $expectedToken = config('services.central_api.token');
        $providedToken = $this->bearerToken();

        return $providedToken === $expectedToken;
    }

    public function rules(): array
    {
        return [
            'tenant_id'       => ['required', 'string', 'exists:tenants,id'],
            'order_id'        => ['required', 'integer'],
            'status'          => ['required', 'string', 'in:confirmed,failed'],
            'reason'          => ['nullable', 'string', 'max:500'],
            'tenant_response' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'tenant_id.exists' => 'The specified tenant does not exist.',
            'status.in'        => 'Status must be either confirmed or failed.',
        ];
    }
}
