<?php

namespace App\Http\Requests\Tenant\Business;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOperatingHoursRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'operating_hours' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'operating_hours.array' => 'Operating hours must be an array.',
        ];
    }

    /**
     * Custom validation for provided days only.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $operatingHours = $this->operating_hours;

            if (!$operatingHours || !is_array($operatingHours)) {
                return;
            }

            $validDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

            // Validate only the days that are provided
            foreach ($operatingHours as $day => $dayData) {
                // Check if it's a valid day
                if (!in_array($day, $validDays)) {
                    $validator->errors()->add(
                        "operating_hours.{$day}",
                        "Invalid day '{$day}'. Valid days are: " . implode(', ', $validDays)
                    );
                    continue;
                }

                // Check if dayData is an array
                if (!is_array($dayData)) {
                    $validator->errors()->add(
                        "operating_hours.{$day}",
                        ucfirst($day) . " must be an array."
                    );
                    continue;
                }

                // Check if day is marked as closed
                $isClosed = isset($dayData['closed']) && $dayData['closed'] === true;

                if ($isClosed) {
                    // If closed, we don't need open/close times
                    continue;
                }

                // If not closed, validate open time
                if (!isset($dayData['open']) || empty($dayData['open'])) {
                    $validator->errors()->add(
                        "operating_hours.{$day}.open",
                        "Opening time is required for " . ucfirst($day) . " unless marked as closed."
                    );
                } elseif (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $dayData['open'])) {
                    $validator->errors()->add(
                        "operating_hours.{$day}.open",
                        "Opening time for " . ucfirst($day) . " must be in HH:MM format (e.g., 08:00)."
                    );
                }

                // If not closed, validate close time
                if (!isset($dayData['close']) || empty($dayData['close'])) {
                    $validator->errors()->add(
                        "operating_hours.{$day}.close",
                        "Closing time is required for " . ucfirst($day) . " unless marked as closed."
                    );
                } elseif (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $dayData['close'])) {
                    $validator->errors()->add(
                        "operating_hours.{$day}.close",
                        "Closing time for " . ucfirst($day) . " must be in HH:MM format (e.g., 20:00)."
                    );
                }

                // Validate that close time is after open time
                if (
                    isset($dayData['open']) &&
                    isset($dayData['close']) &&
                    !empty($dayData['open']) &&
                    !empty($dayData['close'])
                ) {
                    if (strtotime($dayData['close']) <= strtotime($dayData['open'])) {
                        $validator->errors()->add(
                            "operating_hours.{$day}.close",
                            "Closing time for " . ucfirst($day) . " must be after opening time."
                        );
                    }
                }
            }
        });
    }
}
