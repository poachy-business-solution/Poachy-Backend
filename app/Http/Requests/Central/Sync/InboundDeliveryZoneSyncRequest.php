<?php

namespace App\Http\Requests\Central\Sync;

use Illuminate\Foundation\Http\FormRequest;

class InboundDeliveryZoneSyncRequest extends FormRequest
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
            'tenant_id'      => ['required', 'string', 'exists:tenants,id'],
            'action'         => ['required', 'string', 'in:create,update,delete'],
            'priority'       => ['required', 'integer', 'min:1', 'max:10'],
            'idempotency_key' => ['required', 'string', 'max:100'],

            'payload'                         => ['required', 'array'],
            'payload.zone_id'                 => ['required', 'integer'],
            'payload.zone_name'               => ['required', 'string', 'max:255'],
            'payload.zone_type'               => ['required', 'string', 'in:city,county,postal_code,radius'],
            'payload.standard_fee'            => ['required', 'numeric', 'min:0'],
            'payload.supported_methods'       => ['required', 'array'],
            'payload.supported_methods.*'     => ['string'],
            'payload.priority'                => ['required', 'integer', 'min:0'],
            'payload.is_active'               => ['required', 'boolean'],

            'payload.cities'                  => ['nullable', 'array'],
            'payload.counties'                => ['nullable', 'array'],
            'payload.postal_codes'            => ['nullable', 'array'],
            'payload.latitude'                => ['nullable', 'numeric'],
            'payload.longitude'               => ['nullable', 'numeric'],
            'payload.radius_km'               => ['nullable', 'integer', 'min:1'],
            'payload.express_fee'             => ['nullable', 'numeric', 'min:0'],
            'payload.scheduled_fee'           => ['nullable', 'numeric', 'min:0'],
            'payload.free_delivery_threshold' => ['nullable', 'numeric', 'min:0'],
            'payload.standard_delivery_time'  => ['nullable', 'string', 'max:100'],
            'payload.express_delivery_time'   => ['nullable', 'string', 'max:100'],
            'payload.scheduled_delivery_time' => ['nullable', 'string', 'max:100'],

            'metadata' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'tenant_id.exists'                  => 'The specified tenant does not exist.',
            'action.in'                         => 'Invalid sync action. Must be create, update, or delete.',
            'payload.zone_id.required'          => 'Zone ID is required in payload.',
            'payload.zone_name.required'        => 'Zone name is required in payload.',
            'payload.zone_type.in'              => 'Zone type must be city, county, postal_code, or radius.',
            'payload.supported_methods.required' => 'Supported methods are required in payload.',
        ];
    }
}
