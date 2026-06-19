<?php

namespace App\Http\Requests\Central\Review;

use App\Rules\Central\ValidRatingIncrement;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id'       => ['nullable', 'integer', 'exists:central.marketplace_orders,id'],
            'rating'         => ['required', 'numeric', new ValidRatingIncrement],
            'title'          => ['nullable', 'string', 'max:150'],
            'review_text'    => ['required', 'string', 'min:10', 'max:2000'],
            'review_images'  => ['nullable', 'array', 'max:5'],
            'review_images.*' => ['image', 'mimes:jpeg,png,jpg', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'rating.required'          => 'A rating is required.',
            'review_text.required'     => 'Review text is required.',
            'review_text.min'          => 'Review text must be at least 10 characters.',
            'review_text.max'          => 'Review text may not exceed 2,000 characters.',
            'title.max'                => 'Review title may not exceed 150 characters.',
            'review_images.max'        => 'You may upload a maximum of 5 images.',
            'review_images.*.image'    => 'Each upload must be a valid image file.',
            'review_images.*.mimes'    => 'Images must be JPEG, PNG, or JPG.',
            'review_images.*.max'      => 'Each image may not exceed 5MB.',
            'order_id.exists'          => 'The specified order could not be found.',
        ];
    }

    public function attributes(): array
    {
        return [
            'order_id'      => 'order',
            'review_images' => 'review images',
        ];
    }
}
