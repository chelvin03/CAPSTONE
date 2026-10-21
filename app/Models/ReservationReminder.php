<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationReminder extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['attempted_at' => 'datetime', 'sent_at' => 'datetime', 'attempts' => 'integer'];
    public function reservation() { return $this->belongsTo(Reservation::class); }
}
