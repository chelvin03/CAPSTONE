<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class LoginVerificationCode extends Mailable
{
    public function __construct(public readonly string $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your MCST Gymnasium login code');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.login-verification-code');
    }
}
