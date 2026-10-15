<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcknowledgementReceipt extends Model
{
    protected $guarded = [];
    protected $casts = ['snapshot' => 'array', 'prepared_at' => 'datetime', 'sent_at' => 'datetime'];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }
}
