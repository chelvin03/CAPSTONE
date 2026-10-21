<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationEventOutcome extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['schedule' => 'array', 'actual_attendees' => 'integer', 'expected_attendees' => 'integer', 'revision' => 'integer', 'recorded_at' => 'datetime'];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
