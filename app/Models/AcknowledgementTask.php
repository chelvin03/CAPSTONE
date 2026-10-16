<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcknowledgementTask extends Model
{
    protected $fillable = ['reservation_id', 'status', 'completed_by', 'completed_at'];
    protected $casts = ['completed_at' => 'datetime'];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
