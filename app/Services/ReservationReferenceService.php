<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Reservation;
use Illuminate\Support\Facades\DB;

class ReservationReferenceService
{
    public function next(): string
    {
        return DB::transaction(function (): string {
            // Both booking paths hold this lock until their reservation is committed.
            $sequence = DB::table('reservation_reference_sequences')->where('id', 1)->lockForUpdate()->first();
            $number = (int) $sequence->last_number;

            do {
                $reference = $this->format(++$number);
            } while (Reservation::where('reference_number', $reference)->exists());

            DB::table('reservation_reference_sequences')->where('id', 1)->update(['last_number' => $number]);

            return $reference;
        });
    }

    public function format(int $number): string
    {
        if ($number < 1) {
            throw new \InvalidArgumentException('Reservation order must be positive.');
        }

        $batch = intdiv($number - 1, 100) + 1;
        $letters = '';
        while ($batch > 0) {
            $batch--;
            $letters = chr(65 + $batch % 26).$letters;
            $batch = intdiv($batch, 26);
        }

        return 'R'.(($number - 1) % 100 + 1).'-'.$letters;
    }
}
