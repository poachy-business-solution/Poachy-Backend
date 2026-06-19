<?php

namespace App\Http\Requests\Central\Sync;

use App\Enums\Central\OrderFulfillmentStatus;
use Illuminate\Foundation\Http\FormRequest;

class InboundOrderStatusUpdateRequest extends FormRequest
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
            'tenant_id'          => ['required', 'string', 'exists:tenants,id'],
            'order_id'           => ['required', 'integer'],
            'fulfillment_status' => ['required', 'string', 'in:' . implode(',', OrderFulfillmentStatus::values())],
            'notes'              => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'tenant_id.exists'       => 'The specified tenant does not exist.',
            'fulfillment_status.in'  => 'Invalid fulfillment status.',
        ];
    }
}
