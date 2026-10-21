<?php

namespace App\Services;

use App\Mail\ReservationCancelled;
use App\Models\AuditLog;
use App\Models\Reservation;
use App\Models\ReservationStatusHistory;

class ReservationCancellationService
{
    public function cancel(Reservation $reservation, int $adminId, string $reason): void
    {
        if (in_array($reservation->status, ['cancelled', 'rejected', 'completed', 'no_show'], true)) {
            return;
        }
        $previous = $reservation->status;
        $reservation->update(['status' => 'cancelled', 'cancellation_reason' => $reason,
            'cancelled_by' => $adminId, 'cancelled_at' => now()]);
        ReservationStatusHistory::create(['reservation_id' => $reservation->id, 'changed_by' => $adminId,
            'previous_status' => $previous, 'new_status' => 'cancelled', 'remarks' => $reason]);
        AuditLog::record('reservation.cancelled', $reservation, ['previous_status' => $previous, 'reason' => $reason]);
        $this->notify($reservation);
    }

    public function notify(Reservation $reservation): void
    {
        $eligible = app(WaitingListService::class)->eligibleForReleasedSlot($reservation);
        AuditLog::record('reservation.released_slot_review', $reservation, ['eligible_waiting_reservation_ids' => $eligible->pluck('id')->all(),
            'remarks' => 'Availability is provisional and must be checked again at administrator approval.']);
        app(ReservationStatusNotificationService::class)->send($reservation, new ReservationCancelled($reservation), 'Your reservation has been cancelled. Your reason has been recorded.');
    }
}
