<?php
namespace App\Services;

use App\Mail\ReservationApproved;
use App\Models\Reservation;
use App\Notifications\ReservationStatusUpdate;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\{DB, Log, Mail};

class ReservationStatusNotificationService
{
    public function approved(Reservation $reservation): void
    {
        $this->send($reservation, new ReservationApproved($reservation), 'Your reservation has been approved. Your gymnasium schedule is confirmed.');
    }

    public function send(Reservation $reservation, Mailable $mail, string $message): void
    {
        $notice = new ReservationStatusUpdate(['status' => $reservation->status, 'message' => $message,
            'mail_status' => $reservation->contact_email ? 'pending' : 'no_email']);
        $notice->id = (string) \Illuminate\Support\Str::uuid();
        $reservation->notify($notice);
        DB::afterCommit(function () use ($reservation, $mail, $notice): void {
            if (! $reservation->contact_email) return;
            $status = 'sent';
            try {
                Mail::to($reservation->contact_email)->send($mail);
            } catch (\Throwable $exception) {
                $status = 'failed';
                Log::error('Reservation status email failed.', ['reservation_id' => $reservation->id, 'exception_type' => get_class($exception)]);
            }
            $row = $reservation->notifications()->find($notice->id);
            if ($row) $row->update(['data' => array_merge($row->data, ['mail_status' => $status])]);
        });
    }
}
