<?php
namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Content, Envelope};

class ReservationApproved extends Mailable
{
    public function __construct(public Reservation $reservation) {}
    public function envelope(): Envelope { return new Envelope(subject: 'MCST Gym Reservation Approved'); }
    public function content(): Content { return new Content(view: 'emails.reservation-approved'); }
}
