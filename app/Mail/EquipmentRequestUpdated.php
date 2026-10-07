<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class EquipmentRequestUpdated extends Mailable
{
    public function __construct(public Reservation $reservation, public array $details, public string $trackingUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'MCST Gymnasium Equipment Request Update');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.equipment-request-updated');
    }
}
