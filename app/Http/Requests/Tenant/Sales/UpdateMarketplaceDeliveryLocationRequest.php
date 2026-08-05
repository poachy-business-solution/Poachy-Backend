<?php

namespace App\Http\Requests\Tenant\Sales;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMarketplaceDeliveryLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-marketplace-sales') ?? false;
    }

    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }
}
