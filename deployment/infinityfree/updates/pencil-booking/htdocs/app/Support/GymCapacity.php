<?php

declare(strict_types=1);

namespace App\Support;

final class GymCapacity
{
    public const MAX_ATTENDEES = 2000;
    public const ERROR = 'The expected number of attendees cannot exceed the MCST Gymnasium maximum capacity of 2,000 persons.';

    public static function rules(): array
    {
        return ['required', 'integer', 'min:1', 'max:'.self::MAX_ATTENDEES];
    }
}
