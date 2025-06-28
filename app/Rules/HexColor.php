<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class HexColor implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            $fail('The :attribute must be a valid hex color code.');
            return;
        }

        // Check if it's a valid hex color format: #RRGGBB or #RRGGBBAA
        if (!preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{8})$/', $value)) {
            $fail('The :attribute must be a valid hex color code (e.g., #FF0000 or #FF0000FF).');
            return;
        }
    }
}
