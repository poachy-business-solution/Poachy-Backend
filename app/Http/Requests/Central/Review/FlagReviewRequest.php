<?php

namespace App\Http\Requests\Central\Review;

use Illuminate\Foundation\Http\FormRequest;

class FlagReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'A reason for flagging is required.',
            'reason.min'      => 'Reason must be at least 10 characters.',
            'reason.max'      => 'Reason may not exceed 500 characters.',
        ];
    }
}
