<?php

namespace App\Http\Requests\Central\Sync;

use Illuminate\Foundation\Http\FormRequest;

class InboundReviewResponseSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id'     => ['required', 'string', 'exists:tenants,id'],
            'review_id'     => ['required', 'integer'],
            'response_text' => ['required', 'string', 'min:10', 'max:1000'],
            'metadata'      => ['sometimes', 'array'],
        ];
    }
}
