<?php

namespace App\Http\Requests\Central\Marketplace\Analytics;

use Illuminate\Foundation\Http\FormRequest;

class TrackSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search_query'     => ['required', 'string', 'max:255'],
            'session_id'       => ['required', 'string', 'uuid'],
            // 'customer_id'      => ['nullable', 'exists:marketplace_customers,id'],
            'results_count'    => ['required', 'integer', 'min:0'],
            'filters_applied'  => ['nullable', 'array'],
            'parent_search_id' => ['nullable', 'exists:search_queries,id'],
        ];
    }
}
