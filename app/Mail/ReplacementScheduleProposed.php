<?php

namespace App\Mail;

use App\Models\ScheduleProposal;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ReplacementScheduleProposed extends Mailable
{
    public function __construct(public ScheduleProposal $proposal, public string $link) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->proposal->kind === 'waiting_list' ? 'MCST Gym: Available slot offered for your waiting-list request' : 'MCST Gym: Please review your proposed replacement schedule');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.replacement-schedule-proposed');
    }
}
