<?php

namespace App\Http\Requests\Central\Marketplace\Analytics;

use Illuminate\Foundation\Http\FormRequest;

class TrackProductViewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $postRules = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'product_id'        => [$postRules, 'exists:marketplace_products,id'],
            'session_id'        => [$postRules, 'string', 'uuid'],
            'referrer_source'   => ['nullable', 'string', 'in:search,category,home,external'],
            'referrer_url'      => ['nullable', 'url:http,https'],
            'search_query'      => ['nullable', 'string', 'max:255'],
            'device_type'       => ['nullable', 'string', 'in:mobile,tablet,desktop'],
            'browser'           => ['nullable', 'string', 'max:100'],
            'platform'          => ['nullable', 'string', 'max:100'],

            // Update-only fields
            'time_spent_seconds'       => ['sometimes', 'integer', 'min:0'],
            'scrolled_to_description'  => ['sometimes', 'boolean'],
            'scrolled_to_reviews'      => ['sometimes', 'boolean'],
            'clicked_images'           => ['sometimes', 'boolean'],
        ];
    }
}
