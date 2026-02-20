<?php

namespace App\Http\Requests\Central\Review;

use Illuminate\Foundation\Http\FormRequest;

class ModerateReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action'           => ['required', 'string', 'in:approve,reject,flag,dismiss_flags'],
            'rejection_reason' => ['required_if:action,reject', 'nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'action.required'                  => 'A moderation action is required.',
            'action.in'                        => 'Action must be one of: approve, reject, flag, dismiss_flags.',
            'rejection_reason.required_if'     => 'A rejection reason is required when rejecting a review.',
            'rejection_reason.max'             => 'Rejection reason may not exceed 500 characters.',
        ];
    }
}
