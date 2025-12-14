<?php

namespace App\Http\Requests\Tenant\Business;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDeliveryInfoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'delivery_info' => ['nullable', 'array'],
            'delivery_info.available' => ['nullable', 'boolean'],
            'delivery_info.areas' => ['nullable', 'array'],
            'delivery_info.areas.*' => ['string', 'max:100'],
            'delivery_info.fee' => ['nullable', 'numeric', 'min:0'],
            'delivery_info.free_delivery_threshold' => ['nullable', 'numeric', 'min:0'],
            'delivery_info.estimated_time' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'delivery_info.array' => 'Delivery information must be an array.',
            'delivery_info.available.boolean' => 'Delivery availability must be true or false.',
            'delivery_info.areas.array' => 'Delivery areas must be an array.',
            'delivery_info.areas.*.string' => 'Each delivery area must be a string.',
            'delivery_info.areas.*.max' => 'Delivery area name cannot exceed 100 characters.',
            'delivery_info.fee.numeric' => 'Delivery fee must be a number.',
            'delivery_info.fee.min' => 'Delivery fee cannot be negative.',
            'delivery_info.free_delivery_threshold.numeric' => 'Free delivery threshold must be a number.',
            'delivery_info.free_delivery_threshold.min' => 'Free delivery threshold cannot be negative.',
            'delivery_info.estimated_time.max' => 'Estimated delivery time cannot exceed 100 characters.',
        ];
    }

    /**
     * Custom validation logic.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $deliveryInfo = $this->delivery_info;

            if (!$deliveryInfo) {
                return;
            }

            // If delivery is being set to available, ensure all required fields are present
            if (isset($deliveryInfo['available']) && $deliveryInfo['available']) {
                if (!isset($deliveryInfo['areas']) || empty($deliveryInfo['areas']) || count($deliveryInfo['areas']) === 0) {
                    $validator->errors()->add(
                        'delivery_info.areas',
                        'At least one delivery area must be specified when delivery is available.'
                    );
                }

                if (!isset($deliveryInfo['fee'])) {
                    $validator->errors()->add(
                        'delivery_info.fee',
                        'Delivery fee is required when delivery is available.'
                    );
                }

                if (!isset($deliveryInfo['estimated_time']) || empty($deliveryInfo['estimated_time'])) {
                    $validator->errors()->add(
                        'delivery_info.estimated_time',
                        'Estimated delivery time is required when delivery is available.'
                    );
                }

                // Validate free delivery threshold is greater than fee
                if (
                    isset($deliveryInfo['free_delivery_threshold']) &&
                    isset($deliveryInfo['fee']) &&
                    $deliveryInfo['free_delivery_threshold'] > 0 &&
                    $deliveryInfo['free_delivery_threshold'] < $deliveryInfo['fee']
                ) {
                    $validator->errors()->add(
                        'delivery_info.free_delivery_threshold',
                        'Free delivery threshold should be greater than the delivery fee.'
                    );
                }
            }
        });
    }
}
