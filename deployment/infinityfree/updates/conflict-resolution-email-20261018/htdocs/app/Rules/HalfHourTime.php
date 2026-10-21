<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class HalfHourTime implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^(?:(?:[01][0-9]|2[0-3]):(?:00|30)|24:00)$/D', $value)) {
            $fail('Choose a time on the hour or half-hour. Reservations must be at least 1 hour.');
        }
    }
}
