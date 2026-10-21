<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class EquipmentQuantity implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ((! is_int($value) && ! is_string($value)) || ! preg_match('/^[1-9][0-9]*$/D', (string) $value)) {
            $fail(':attribute must be a positive whole number without leading zeros (for example, 10 instead of 010).');
        }
    }
}
