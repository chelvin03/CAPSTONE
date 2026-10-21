<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduleProposal extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['original' => 'array', 'target' => 'array', 'expires_at' => 'datetime', 'responded_at' => 'datetime'];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }
}
