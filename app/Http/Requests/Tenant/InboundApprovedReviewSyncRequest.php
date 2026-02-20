<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class InboundApprovedReviewSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'review_id'            => ['required', 'integer'],
            'tenant_id'            => ['required', 'string'],
            'product_id'           => ['required', 'integer'],
            'product_name'         => ['required', 'string'],
            'product_sku'          => ['nullable', 'string'],
            'customer_name'        => ['required', 'string'],
            'rating'               => ['required', 'numeric', 'min:1', 'max:5'],
            'title'                => ['nullable', 'string'],
            'review_text'          => ['nullable', 'string'],
            'review_images'        => ['nullable', 'array'],
            'is_verified_purchase' => ['required', 'boolean'],
            'reviewed_at'          => ['required', 'string'],
        ];
    }
}
