<?php

namespace App\Http\Requests\Tenant\Marketplace;

use Illuminate\Foundation\Http\FormRequest;

class ListShopPayoutsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route middleware: view-financial-reports
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
