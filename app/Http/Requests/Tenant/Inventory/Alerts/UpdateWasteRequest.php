<?php

namespace App\Http\Requests\Tenant\Inventory\Alerts;

use App\Enums\Tenant\WasteType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateWasteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-waste-records') || $this->user()->can('view-waste-records');
    }

    public function rules(): array
    {
        return [
            'waste_type' => ['sometimes', new Enum(WasteType::class)],
            'quantity_wasted' => ['sometimes', 'numeric', 'min:0.0001', 'max:999999.9999'],
            'waste_date' => ['sometimes', 'date', 'before_or_equal:today'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
