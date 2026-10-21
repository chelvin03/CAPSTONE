<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentReturn extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['issued' => 'integer', 'returned_good' => 'integer', 'damaged' => 'integer', 'missing' => 'integer', 'restored' => 'integer', 'revision' => 'integer'];

    public function equipment() { return $this->belongsTo(Equipment::class); }
    public function reservation() { return $this->belongsTo(Reservation::class); }
    public function recorder() { return $this->belongsTo(User::class, 'recorded_by'); }
}
