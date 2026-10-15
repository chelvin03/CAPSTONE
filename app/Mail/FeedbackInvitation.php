<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class FeedbackInvitation extends Mailable
{
    public function __construct(public string $feedbackUrl) {}

    public function build()
    {
        return $this->subject('MCST Gymnasium — Share Your Feedback')->view('emails.feedback-invitation');
    }
}
