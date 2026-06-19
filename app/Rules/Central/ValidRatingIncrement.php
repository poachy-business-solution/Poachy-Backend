<?php

namespace App\Rules\Central;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidRatingIncrement implements ValidationRule
{
    /**
     * Validates that the rating is between 1.0 and 5.0 in 0.5 increments.
     * Valid values: 1.0, 1.5, 2.0, 2.5, 3.0, 3.5, 4.0, 4.5, 5.0
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = (float) $value;

        if ($value < 1.0 || $value > 5.0) {
            $fail('The :attribute must be between 1.0 and 5.0.');

            return;
        }

        // Multiply by 2 to convert 0.5 increments to whole numbers, then check for remainder
        if (fmod($value * 2, 1) !== 0.0) {
            $fail('The :attribute must be in 0.5 increments (e.g., 1.0, 1.5, 2.0, 2.5).');
        }
    }
}
