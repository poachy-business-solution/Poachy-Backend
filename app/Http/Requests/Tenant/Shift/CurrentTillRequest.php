<?php

namespace App\Http\Requests\Tenant\Shift;

use Illuminate\Foundation\Http\FormRequest;

class CurrentTillRequest extends FormRequest
{
    /**
     * Any signed-in staff member may see their own till.
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
            'store_id' => ['required', 'integer', 'exists:stores,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'store_id.required' => 'Choose the store you are selling from.',
            'store_id.exists' => 'Selected store does not exist.',
        ];
    }
}
