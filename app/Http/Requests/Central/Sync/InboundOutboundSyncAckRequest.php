<?php

namespace App\Http\Requests\Central\Sync;

use Illuminate\Foundation\Http\FormRequest;

class InboundOutboundSyncAckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->bearerToken() === config('services.central_api.token');
    }

    public function rules(): array
    {
        return [
            'outbound_sync_id' => ['required', 'integer'],
            'tenant_id'        => ['required', 'string', 'exists:tenants,id'],
            'status'           => ['required', 'string', 'in:completed,failed'],
            'reason'           => ['nullable', 'string', 'max:500'],
            'tenant_record_id' => ['nullable', 'integer'],
            'tenant_table'     => ['nullable', 'string', 'max:100'],
            'tenant_response'  => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'tenant_id.exists'          => 'The specified tenant does not exist.',
            'status.in'                 => 'Status must be either completed or failed.',
            'outbound_sync_id.required' => 'An outbound sync ID is required.',
        ];
    }
}
