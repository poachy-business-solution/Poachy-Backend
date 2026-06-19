<?php

namespace App\Http\Requests\Central\Review;

use App\Rules\Central\ValidRatingIncrement;
use Illuminate\Foundation\Http\FormRequest;

class StoreMerchantReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'overall_rating'         => ['required', 'numeric', new ValidRatingIncrement],
            'product_quality_rating' => ['nullable', 'numeric', new ValidRatingIncrement],
            'delivery_rating'        => ['nullable', 'numeric', new ValidRatingIncrement],
            'service_rating'         => ['nullable', 'numeric', new ValidRatingIncrement],
            'review_text'            => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'overall_rating.required'         => 'An overall rating is required.',
            // 'product_quality_rating.required' => 'A product quality rating is required.',
            // 'delivery_rating.required'        => 'A delivery rating is required.',
            'review_text.required'            => 'Review text is required.',
            'review_text.min'                 => 'Review text must be at least 10 characters.',
            'review_text.max'                 => 'Review text may not exceed 2,000 characters.',
        ];
    }

    public function attributes(): array
    {
        return [
            'overall_rating'         => 'overall rating',
            'product_quality_rating' => 'product quality rating',
            'delivery_rating'        => 'delivery rating',
            'service_rating'         => 'service rating',
        ];
    }
}
