<?php

declare(strict_types=1);

namespace App\Support;

class PublicBookingWindow
{
    public static function maximumDate(): string
    {
        return now()->addMonthsNoOverflow(2)->toDateString();
    }
}
