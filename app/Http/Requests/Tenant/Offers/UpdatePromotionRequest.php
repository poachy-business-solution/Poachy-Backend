<?php

namespace App\Http\Requests\Tenant\Offers;

use App\Enums\Tenant\DayOfWeek;
use App\Enums\Tenant\PromotionApplicabilityType;
use App\Enums\Tenant\PromotionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-promotions');
    }

    public function rules(): array
    {
        $promotionId = $this->route('id');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => [
                'sometimes',
                'string',
                'max:50',
                'regex:/^[A-Z0-9-_]+$/',
                Rule::unique('promotions', 'code')->ignore($promotionId)->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'promotion_type' => ['sometimes', Rule::enum(PromotionType::class)],
            'discount_value' => ['nullable', 'numeric', 'min:0.01', 'max:999999.99'],
            'buy_quantity' => ['nullable', 'integer', 'min:1'],
            'get_quantity' => ['nullable', 'integer', 'min:1'],
            'get_items_free' => ['sometimes', 'boolean'],
            'get_items_discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'min_purchase_amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'max_uses_per_customer' => ['nullable', 'integer', 'min:1'],
            'total_usage_limit' => ['nullable', 'integer', 'min:1'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after:start_date'],
            'active_days' => ['nullable', 'array'],
            'active_days.*' => ['string', Rule::in(DayOfWeek::values())],
            'active_time_start' => ['nullable', 'date_format:H:i'],
            'active_time_end' => ['nullable', 'date_format:H:i', 'after:active_time_start'],
            'applicable_store_ids' => ['nullable', 'array'],
            'applicable_store_ids.*' => ['integer', 'exists:stores,id'],
            'applicable_customer_group_ids' => ['nullable', 'array'],
            'applicable_customer_group_ids.*' => ['integer', 'exists:customer_groups,id'],
            'applicable_to' => ['sometimes', Rule::enum(PromotionApplicabilityType::class)],
            'show_on_website' => ['sometimes', 'boolean'],
            'show_in_pos' => ['sometimes', 'boolean'],
            'banner_image_url' => ['nullable', 'url', 'max:500'],
            'display_priority' => ['sometimes', 'integer', 'min:0', 'max:999'],
            'is_active' => ['sometimes', 'boolean'],
            'auto_apply' => ['sometimes', 'boolean'],
            'applicability' => ['sometimes', 'array'],
            'applicability.products' => ['sometimes', 'array'],
            'applicability.products.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'applicability.products.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'applicability.categories' => ['sometimes', 'array'],
            'applicability.categories.*' => ['required', 'integer', 'exists:product_categories,id'],
            'applicability.brands' => ['sometimes', 'array'],
            'applicability.brands.*' => ['required', 'integer', 'exists:product_brands,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->has('promotion_type')) {
                $promotionType = PromotionType::tryFrom($this->promotion_type);

                if ($promotionType === PromotionType::PERCENTAGE_DISCOUNT && $this->has('discount_value')) {
                    if ($this->discount_value > 100) {
                        $validator->errors()->add('discount_value', 'Percentage discount cannot exceed 100%.');
                    }
                }
            }
        });
    }
}
