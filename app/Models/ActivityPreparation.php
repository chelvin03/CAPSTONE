<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityPreparation extends Model
{
    protected $fillable = ['reservation_id', 'task', 'completed', 'snapshot_hash', 'updated_by'];

    protected $casts = ['completed' => 'boolean'];

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
