<?php

namespace App\Http\Requests\Public;

use App\Services\Central\Marketplace\PublicMarketService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Filters for category and search pages. */
class MarketListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'string', 'min:2', 'max:100'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'gte:min_price'],
            'in_stock' => ['nullable', 'boolean'],
            'county' => ['nullable', 'string', 'max:60'],
            'sort' => ['nullable', Rule::in(PublicMarketService::SORTS)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.PublicMarketService::MAX_PER_PAGE],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
