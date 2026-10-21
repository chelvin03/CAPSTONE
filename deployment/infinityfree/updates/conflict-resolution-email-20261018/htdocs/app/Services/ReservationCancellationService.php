<?php

namespace App\Services;

use App\Mail\ReservationCancelled;
use App\Models\AuditLog;
use App\Models\Reservation;
use App\Models\ReservationStatusHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ReservationCancellationService
{
    public function cancel(Reservation $reservation, int $adminId, string $reason): void
    {
        if (in_array($reservation->status, ['cancelled', 'rejected', 'completed'], true)) {
            return;
        }
        $previous = $reservation->status;
        $reservation->update(['status' => 'cancelled', 'cancellation_reason' => $reason,
            'cancelled_by' => $adminId, 'cancelled_at' => now()]);
        ReservationStatusHistory::create(['reservation_id' => $reservation->id, 'changed_by' => $adminId,
            'previous_status' => $previous, 'new_status' => 'cancelled', 'remarks' => $reason]);
        AuditLog::record('reservation.cancelled', $reservation, ['previous_status' => $previous, 'reason' => $reason]);

        DB::afterCommit(function () use ($reservation): void {
            if (! $reservation->contact_email) {
                return;
            }
            try {
                Mail::to($reservation->contact_email)->send(new ReservationCancelled($reservation));
            } catch (\Throwable $exception) {
                Log::error('Reservation cancellation email failed.', ['reservation_id' => $reservation->id, 'exception_type' => get_class($exception)]);
            }
        });
    }
}
