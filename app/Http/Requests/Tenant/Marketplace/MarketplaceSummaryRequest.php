<?php

namespace App\Http\Requests\Tenant\Marketplace;

use Illuminate\Foundation\Http\FormRequest;

class MarketplaceSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route middleware: view-marketplace-sales
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
        ];
    }
}
