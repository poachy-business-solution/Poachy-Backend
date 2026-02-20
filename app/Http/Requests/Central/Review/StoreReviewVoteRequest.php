<?php

namespace App\Http\Requests\Central\Review;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewVoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vote_type' => ['required', 'string', 'in:helpful,not_helpful'],
        ];
    }

    public function messages(): array
    {
        return [
            'vote_type.required' => 'A vote type is required.',
            'vote_type.in'       => 'Vote type must be either "helpful" or "not_helpful".',
        ];
    }
}
