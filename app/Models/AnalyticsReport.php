<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsReport extends Model
{
    protected $fillable = [
        'submitted_by', 'report_type', 'period_start', 'period_end',
        'total_reservations', 'approved_reservations', 'completed_reservations',
        'total_attendees', 'status_counts', 'facility_counts', 'submitted_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'status_counts' => 'array',
        'facility_counts' => 'array',
        'submitted_at' => 'datetime',
    ];

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
