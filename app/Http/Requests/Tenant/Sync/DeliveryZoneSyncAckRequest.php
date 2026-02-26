<?php

namespace App\Http\Requests\Tenant\Sync;

use Illuminate\Foundation\Http\FormRequest;

class DeliveryZoneSyncAckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'outbound_sync_queue_id' => ['required', 'integer'],
            'status'                 => ['required', 'string', 'in:completed,failed'],
            'central_zone_id'        => ['required_if:status,completed', 'nullable', 'integer'],
            'reason'                 => ['nullable', 'string', 'max:500'],
        ];
    }
}
