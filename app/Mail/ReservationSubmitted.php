<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ReservationSubmitted extends Mailable
{
    public function __construct(
        public Reservation $reservation,
        public string $trackingUrl,
        public string $intro,
        public string $subjectLine,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.reservation-submitted');
    }
}
