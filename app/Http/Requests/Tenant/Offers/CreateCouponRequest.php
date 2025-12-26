<?php

namespace App\Http\Requests\Tenant\Offers;

use App\Enums\Tenant\CouponApplicabilityType;
use App\Enums\Tenant\DiscountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-coupons');
    }

    public function rules(): array
    {
        $tenantId = tenant()->id;

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9-_]+$/',
                Rule::unique('coupons', 'code')->whereNull('deleted_at'),
            ],
            'description' => ['required', 'string', 'max:500'],
            'discount_type' => ['required', Rule::enum(DiscountType::class)],
            'discount_value' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'min_purchase_amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_customer' => ['nullable', 'integer', 'min:1'],
            'valid_from' => ['required', 'date', 'after_or_equal:today'],
            'valid_until' => ['required', 'date', 'after:valid_from'],
            'applicable_to' => ['required', Rule::enum(CouponApplicabilityType::class)],
            'is_active' => ['sometimes', 'boolean'],

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
            'code.required' => 'Coupon code is required.',
            'code.regex' => 'Coupon code must contain only uppercase letters, numbers, hyphens, and underscores.',
            'code.unique' => 'This coupon code already exists.',
            'discount_type.required' => 'Discount type is required.',
            'discount_value.required' => 'Discount value is required.',
            'discount_value.min' => 'Discount value must be greater than 0.',
            'valid_from.after_or_equal' => 'Valid from date must be today or later.',
            'valid_until.after' => 'Valid until date must be after valid from date.',
            'applicable_to.required' => 'Please specify what this coupon applies to.',
            'applicability.products.*.product_id.exists' => 'One or more selected products do not exist.',
            'applicability.categories.*.exists' => 'One or more selected categories do not exist.',
            'applicability.brands.*.exists' => 'One or more selected brands do not exist.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate percentage discount
            if ($this->discount_type === 'percentage' && $this->discount_value > 100) {
                $validator->errors()->add('discount_value', 'Percentage discount cannot exceed 100%.');
            }

            // Validate applicability data is provided for specific types
            $applicabilityType = CouponApplicabilityType::tryFrom($this->applicable_to);

            if ($applicabilityType && $applicabilityType->requiresRelatedData()) {
                $hasData = match ($applicabilityType) {
                    CouponApplicabilityType::SPECIFIC_PRODUCTS => !empty($this->input('applicability.products')),
                    CouponApplicabilityType::SPECIFIC_CATEGORIES => !empty($this->input('applicability.categories')),
                    CouponApplicabilityType::SPECIFIC_BRANDS => !empty($this->input('applicability.brands')),
                    default => false,
                };

                if (!$hasData) {
                    $validator->errors()->add('applicability', "You must provide at least one {$applicabilityType->label()} for this applicability type.");
                }
            }
        });
    }
}
