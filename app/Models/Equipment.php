<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Equipment extends Model
{
    use HasFactory;

    protected $table = 'equipment';

    protected $fillable = [
        'equipment_name',
        'description',
        'total_quantity',
        'unit',
        'status',
    ];

    protected $casts = [
        'total_quantity' => 'integer',
    ];

    public function reservations(): BelongsToMany
    {
        return $this->belongsToMany(
            Reservation::class,
            'reservation_equipment'
        )
            ->withPivot([
                'quantity_requested',
                'quantity_approved',
                'remarks',
                'status', 'quantity_offered', 'reply_sent_at', 'requires_response',
                'latest_offer_id', 'requestor_response', 'requestor_responded_at',
            ])
            ->withTimestamps();
    }
}
