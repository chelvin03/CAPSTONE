<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ReservationScheduleChanged extends Mailable
{
    public function __construct(public Reservation $reservation, public string $reason, public array $oldSchedule, public string $outcome) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: match ($this->outcome) {
            'waiting_list' => 'MCST Gym Reservation Moved to Waiting List',
            'waiting_list_accepted' => 'MCST Gym Reservation Approved',
            default => 'MCST Gym Reservation Rescheduled',
        });
    }

    public function content(): Content
    {
        return new Content(view: 'emails.reservation-schedule-changed');
    }
}
