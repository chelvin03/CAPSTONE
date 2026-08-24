<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public function reservations(): HasMany
{
    return $this->hasMany(Reservation::class);
}

public function approvedReservations(): HasMany
{
    return $this->hasMany(Reservation::class, 'approved_by');
}

public function rejectedReservations(): HasMany
{
    return $this->hasMany(Reservation::class, 'rejected_by');
}

public function cancelledReservations(): HasMany
{
    return $this->hasMany(Reservation::class, 'cancelled_by');
}

public function uploadedReservationDocuments(): HasMany
{
    return $this->hasMany(ReservationDocument::class, 'uploaded_by');
}

public function reservationStatusChanges(): HasMany
{
    return $this->hasMany(
        ReservationStatusHistory::class,
        'changed_by'
    );
}
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'role',
        'requestor_category',
        'contact_number',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
