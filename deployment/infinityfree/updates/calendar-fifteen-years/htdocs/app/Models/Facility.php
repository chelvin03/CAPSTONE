<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Facility extends Model
{
    use HasFactory;

    public function scopeGym($query)
    {
        return $query->where('id', static::query()
            ->select('id')
            ->whereIn('facility_name', ['MCST Gymnasium', 'MCST Main Gymnasium'])
            ->orderByRaw("CASE WHEN facility_name = 'MCST Gymnasium' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->limit(1));
    }

    public static function gymId(): int
    {
        $gym = static::gym()->first();
        if (! $gym) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'facility_id' => 'MCST Gymnasium is not configured. Please contact the administrator.',
            ]);
        }

        return (int) $gym->id;
    }

public function reservations(): HasMany
{
    return $this->hasMany(Reservation::class);
}

    protected $fillable = [
        'facility_name',
        'description',
        'location',
        'capacity',
        'status',
    ];
}
