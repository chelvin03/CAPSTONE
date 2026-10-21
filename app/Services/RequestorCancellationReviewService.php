<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\RequestorRestriction;

class RequestorCancellationReviewService
{
    public function report(int $days = 90): array
    {
        $from = now()->subDays($days);
        $reservations = Reservation::where('created_at', '>=', $from)->where('created_at', '<=', now())
            ->whereNotNull('contact_email')->with('statusHistories')->get();
        $rows = $reservations->groupBy(fn ($r) => strtolower(trim($r->contact_email)))
            ->filter(fn ($records, $email) => $email !== '')
            ->map(function ($records, $email) use ($from) {
                $cancelled = $records->filter(fn ($r) => $r->status === 'cancelled' && ! $r->cancelled_by
                    && $r->statusHistories->contains(fn ($h) => $h->new_status === 'cancelled' && ! $h->changed_by
                        && $h->created_at->betweenIncluded($from, now())
                        && str_starts_with($h->remarks ?? '', 'Cancelled by requestor:')));
                return ['email' => $email, 'total' => $records->count(), 'cancelled' => $cancelled,
                    'rate' => round($cancelled->count() / $records->count() * 100, 1),
                    'flagged' => $cancelled->count() >= 3];
            })->filter(fn ($row) => $row['cancelled']->isNotEmpty())
            ->sortByDesc(fn ($row) => $row['cancelled']->count())->values();
        return ['reviewRows' => $rows, 'reviewDays' => $days, 'reviewFrom' => $from,
            'restrictions' => RequestorRestriction::orderByDesc('updated_at')->get()];
    }
}
