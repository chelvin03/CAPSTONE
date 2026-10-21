<?php

namespace App\Services;

use App\Models\Reservation;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class WaitingListService
{
    public function assertDailyLimit(int $facilityId, string $date, ?int $except = null): void
    {
        $limit = max(1, (int) config('gym.reservation.daily_limit', 5));
        $count = Reservation::where('facility_id', $facilityId)->whereDate('reservation_date', $date)
            ->whereIn('status', PublicCalendarService::BLOCKING_STATUSES)
            ->when($except, fn ($q) => $q->whereKeyNot($except))->count();
        if ($count >= $limit) {
            throw ValidationException::withMessages(['reservation_date' => 'The daily reservation limit has been reached. Choose another date.']);
        }
    }

    public function eligibleForReleasedSlot(Reservation $released): Collection
    {
        if ($released->status !== 'cancelled') return collect();
        return Reservation::where('facility_id', $released->facility_id)
            ->whereDate('reservation_date', $released->reservation_date)->where('status', 'waiting_list')
            ->where('start_time', '<', $released->end_time)->where('end_time', '>', $released->start_time)
            ->orderBy('created_at')->orderBy('id')->get()->filter(function ($candidate) {
                try {
                    if ($candidate->contact_email) \App\Models\RequestorRestriction::assertCanReserve($candidate->contact_email);
                    app(ScheduleProposalService::class)->assertAvailable($candidate, app(ScheduleProposalService::class)->snapshot($candidate));
                    $this->assertDailyLimit($candidate->facility_id, $candidate->reservation_date->toDateString(), $candidate->id);
                    return true;
                } catch (ValidationException $exception) {
                    return false;
                }
            })->values();
    }
}
