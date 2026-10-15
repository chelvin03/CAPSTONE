<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    protected $table = 'feedback';
    protected $fillable = ['reservation_id', 'user_id', 'rating', 'comments', 'is_anonymous'];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }
}
