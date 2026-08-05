<?php

namespace App\Http\Requests\Central\Marketplace;

use Illuminate\Foundation\Http\FormRequest;

class ReceiveDeliveryLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->bearerToken() === config('services.central_api.token');
    }

    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }
}
