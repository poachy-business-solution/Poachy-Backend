<?php

namespace App\Http\Requests\Central\Marketplace;

use App\Enums\Central\StockStatus;
use Illuminate\Foundation\Http\FormRequest;

class ListMarketplaceProductsRequest extends FormRequest
{
    /**
     * Public marketplace endpoint — no authentication required.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Full-text search
            'search'                => ['nullable', 'string', 'min:2', 'max:100'],

            // Category filtering — marketplace OR tenant (mutually informative, both allowed)
            'marketplace_category_id' => ['nullable', 'integer', 'min:1'],
            'marketplace_brand_id'    => ['nullable', 'integer', 'min:1'],

            // Tenant-scoped filtering (browse a single merchant's online products)
            'tenant_id'             => ['nullable', 'string', 'max:100'],

            // Stock status filter
            'stock_status'          => ['nullable', 'string', 'in:' . implode(',', StockStatus::values())],

            // Featured flag
            'featured'              => ['nullable', 'boolean'],

            // Price range
            'min_price'             => ['nullable', 'numeric', 'min:0'],
            'max_price'             => ['nullable', 'numeric', 'min:0', 'gte:min_price'],

            // Sorting
            'sort_by'               => [
                'nullable',
                'string',
                'in:name,online_price,view_count,order_count,average_rating,display_priority,created_at',
            ],
            'sort_direction'        => ['nullable', 'string', 'in:asc,desc'],

            // Pagination
            'per_page'              => ['nullable', 'integer', 'min:1', 'max:100'],
            'page'                  => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'max_price.gte'           => 'The maximum price must be greater than or equal to the minimum price.',
            'stock_status.in'         => 'Stock status must be one of: ' . implode(', ', StockStatus::values()) . '.',
            'sort_by.in'              => 'Sort field must be one of: name, online_price, view_count, order_count, average_rating, display_priority, created_at.',
            'sort_direction.in'       => 'Sort direction must be asc or desc.',
        ];
    }

    /**
     * Prepare inputs for validation — cast boolean-like strings from query params.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('featured')) {
            $this->merge([
                'featured' => filter_var($this->featured, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
    }
}