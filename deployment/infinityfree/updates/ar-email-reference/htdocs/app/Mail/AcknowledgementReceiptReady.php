<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class AcknowledgementReceiptReady extends Mailable
{
    public function __construct(public string $printUrl, public string $reference) {}

    public function build()
    {
        return $this->subject('AR Ready — '.$this->reference.' | MCST Gymnasium')->view('emails.acknowledgement-ready');
    }
}
