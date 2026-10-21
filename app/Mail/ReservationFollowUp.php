<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Content, Envelope};

class ReservationFollowUp extends Mailable
{
    public function __construct(public array $followUp) {}
    public function envelope(): Envelope { return new Envelope(subject: 'MCST Gym: '.$this->followUp['title']); }
    public function content(): Content { return new Content(view: 'emails.reservation-follow-up'); }
}
