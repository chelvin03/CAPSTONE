<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PublicRegistrationCode extends Mailable
{
    public function __construct(public readonly string $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'MCST Gymnasium Reservation Verification Code');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.public-registration-code');
    }
}
