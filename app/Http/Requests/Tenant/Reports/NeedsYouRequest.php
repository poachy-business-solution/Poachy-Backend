<?php

namespace App\Http\Requests\Tenant\Reports;

use Illuminate\Foundation\Http\FormRequest;

class NeedsYouRequest extends FormRequest
{
    /**
     * Route middleware requires view-financial-reports.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
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

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'store_id.exists' => 'Selected branch does not exist.',
        ];
    }
}
