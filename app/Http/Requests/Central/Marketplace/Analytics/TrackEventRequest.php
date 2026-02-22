<?php

namespace App\Http\Requests\Central\Marketplace\Analytics;

use App\Enums\Central\TrackEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class TrackEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_type' => ['required', new Enum(TrackEvent::class)],
            'session_id'                => ['required', 'string', 'uuid'],
            'marketplace_product_id'    => ['nullable', 'exists:marketplace_products,id'],
            'marketplace_category_id'   => ['nullable', 'integer'],
            'tenant_id'                 => ['nullable', 'string', 'max:255'],
            'event_properties'          => ['nullable', 'array'],
            'page_url'                  => ['nullable', 'url:http,https'],
            'referrer_url'              => ['nullable', 'url:http,https'],
            'time_on_page_seconds'      => ['nullable', 'integer', 'min:0'],
        ];
    }
}
