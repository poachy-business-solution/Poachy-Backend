<?php

namespace App\Http\Requests\Tenant\Inventory\Alerts;

use Illuminate\Foundation\Http\FormRequest;

class ResolveStockAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('resolve-stock-alerts');
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
