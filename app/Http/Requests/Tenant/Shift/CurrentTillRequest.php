<?php

namespace App\Http\Requests\Tenant\Shift;

use App\Rules\Tenant\AssignedStore;
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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'store_id' => ['required', 'integer', 'exists:stores,id', new AssignedStore],
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
