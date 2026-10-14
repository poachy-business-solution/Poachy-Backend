<?php

namespace App\Http\Requests\Tenant\Marketplace;

use App\Enums\Central\MarketplaceReservationStatus;
use Illuminate\Foundation\Http\FormRequest;

class ListReservationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', 'in:'.implode(',', MarketplaceReservationStatus::values())],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
