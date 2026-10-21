<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

class MinimumReservationDuration implements DataAwareRule, ValidationRule
{
    private array $data = [];

    public function setData(array $data): static
    {
        $this->data = $data;
        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $start = $this->data['start_time'] ?? null;
        if (! is_string($start) || ! is_string($value)
            || ! preg_match('/^\d{2}:\d{2}$/D', $start)
            || ! preg_match('/^\d{2}:\d{2}$/D', $value)) {
            return;
        }
        $minutes = fn (string $time) => (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
        if ($minutes($value) - $minutes($start) < 120) {
            $fail('Reservations must be at least 2 hours.');
        }
    }
}
