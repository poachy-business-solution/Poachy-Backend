<?php

namespace App\Http\Requests\Central\Sync;

use Illuminate\Foundation\Http\FormRequest;

/** A shop (tenant) reports whether it could hold the stock for a reserve & collect code. */
class InboundReservationConfirmationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->bearerToken() === config('services.central_api.token');
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'string', 'exists:tenants,id'],
            'reservation_id' => ['required', 'integer'],
            'status' => ['required', 'string', 'in:held,failed'],
            'reason' => ['nullable', 'string', 'max:500'],
            'outbound_sync_id' => ['nullable', 'integer'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'tenant_id.exists' => 'The specified tenant does not exist.',
            'status.in' => 'Status must be either held or failed.',
        ];
    }
}
