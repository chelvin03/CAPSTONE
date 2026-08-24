<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_number',
        'user_id',
        'contact_email',
        'facility_id',
        'reservation_type',
        'event_name',
        'event_type',
        'purpose',
        'contact_person',
        'contact_number',
        'expected_attendees',
        'reservation_date',
        'start_time',
        'end_time',
        'setup_time',
        'cleanup_time',
        'status',
        'priority_number',
        'admin_notes',
        'rejection_reason',
        'cancellation_reason',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'cancelled_by',
        'cancelled_at',
        'completed_at',
    ];

    protected $casts = [
        'reservation_date' => 'date',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'completed_at' => 'datetime',
        'expected_attendees' => 'integer',
        'priority_number' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function equipment(): BelongsToMany
    {
        return $this->belongsToMany(
            Equipment::class,
            'reservation_equipment'
        )
            ->withPivot([
                'quantity_requested',
                'quantity_approved',
                'remarks',
            ])
            ->withTimestamps();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ReservationDocument::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ReservationStatusHistory::class)
            ->latest();
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
