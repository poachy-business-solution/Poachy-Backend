<?php

namespace App\Http\Requests\Tenant\Offers;

use App\Enums\Tenant\DayOfWeek;
use App\Enums\Tenant\PromotionApplicabilityType;
use App\Enums\Tenant\PromotionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-promotions');
    }

    public function rules(): array
    {
        return [
            // Basic Info
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9-_]+$/',
                Rule::unique('promotions', 'code')->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:1000'],

            // Promotion Type
            'promotion_type' => ['required', Rule::enum(PromotionType::class)],

            // Discount Values
            'discount_value' => ['nullable', 'numeric', 'min:0.01', 'max:999999.99'],
            'buy_quantity' => ['nullable', 'integer', 'min:1'],
            'get_quantity' => ['nullable', 'integer', 'min:1'],
            'get_items_free' => ['sometimes', 'boolean'],
            'get_items_discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],

            // Conditions
            'min_purchase_amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'max_uses_per_customer' => ['nullable', 'integer', 'min:1'],
            'total_usage_limit' => ['nullable', 'integer', 'min:1'],

            // Validity
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'active_days' => ['nullable', 'array'],
            'active_days.*' => ['string', Rule::in(DayOfWeek::values())],
            'active_time_start' => ['nullable', 'date_format:H:i'],
            'active_time_end' => ['nullable', 'date_format:H:i', 'after:active_time_start'],

            // Applicability
            'applicable_store_ids' => ['nullable', 'array'],
            'applicable_store_ids.*' => ['integer', 'exists:stores,id'],
            'applicable_customer_group_ids' => ['nullable', 'array'],
            'applicable_customer_group_ids.*' => ['integer', 'exists:customer_groups,id'],
            'applicable_to' => ['required', Rule::enum(PromotionApplicabilityType::class)],

            // Display
            'show_on_website' => ['sometimes', 'boolean'],
            'show_in_pos' => ['sometimes', 'boolean'],
            'banner_image_url' => ['nullable', 'url', 'max:500'],
            'display_priority' => ['sometimes', 'integer', 'min:0', 'max:999'],

            // Status
            'is_active' => ['sometimes', 'boolean'],
            'auto_apply' => ['sometimes', 'boolean'],

            // Applicability data (nested)
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

    public function messages(): array
    {
        return [
            'code.required' => 'Promotion code is required.',
            'code.regex' => 'Promotion code must contain only uppercase letters, numbers, hyphens, and underscores.',
            'code.unique' => 'This promotion code already exists.',
            'promotion_type.required' => 'Promotion type is required.',
            'start_date.after_or_equal' => 'Start date must be today or later.',
            'end_date.after' => 'End date must be after start date.',
            'active_time_end.after' => 'End time must be after start time.',
            'applicable_to.required' => 'Please specify what this promotion applies to.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $promotionType = PromotionType::tryFrom($this->promotion_type);

            if (!$promotionType) {
                return;
            }

            // Validate based on promotion type
            if ($promotionType === PromotionType::PERCENTAGE_DISCOUNT) {
                if (!$this->has('discount_value')) {
                    $validator->errors()->add('discount_value', 'Discount value is required for percentage discount.');
                } elseif ($this->discount_value > 100) {
                    $validator->errors()->add('discount_value', 'Percentage discount cannot exceed 100%.');
                }
            }

            if ($promotionType === PromotionType::FIXED_DISCOUNT) {
                if (!$this->has('discount_value')) {
                    $validator->errors()->add('discount_value', 'Discount value is required for fixed discount.');
                }
            }

            if ($promotionType === PromotionType::BUY_X_GET_Y) {
                if (!$this->has('buy_quantity') || !$this->has('get_quantity')) {
                    $validator->errors()->add('promotion_type', 'Buy quantity and Get quantity are required for Buy X Get Y promotion.');
                }

                if (!$this->get_items_free && !$this->has('get_items_discount_percentage')) {
                    $validator->errors()->add('get_items_discount_percentage', 'Discount percentage is required when get items are not free.');
                }
            }

            // Validate applicability data
            $applicabilityType = PromotionApplicabilityType::tryFrom($this->applicable_to);

            if ($applicabilityType && $applicabilityType->requiresRelatedData()) {
                $hasData = match ($applicabilityType) {
                    PromotionApplicabilityType::SPECIFIC_PRODUCTS => !empty($this->input('applicability.products')),
                    PromotionApplicabilityType::SPECIFIC_CATEGORIES => !empty($this->input('applicability.categories')),
                    PromotionApplicabilityType::SPECIFIC_BRANDS => !empty($this->input('applicability.brands')),
                    default => false,
                };

                if (!$hasData) {
                    $validator->errors()->add('applicability', "You must provide at least one {$applicabilityType->label()} for this applicability type.");
                }
            }

            // Validate time window consistency
            if ($this->has('active_time_start') && !$this->has('active_time_end')) {
                $validator->errors()->add('active_time_end', 'End time is required when start time is specified.');
            }

            if (!$this->has('active_time_start') && $this->has('active_time_end')) {
                $validator->errors()->add('active_time_start', 'Start time is required when end time is specified.');
            }
        });
    }
}
