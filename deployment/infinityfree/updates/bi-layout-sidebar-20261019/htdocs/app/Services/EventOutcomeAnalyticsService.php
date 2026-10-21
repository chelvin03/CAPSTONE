<?php

namespace App\Services;

class EventOutcomeAnalyticsService
{
    public function summarize(array $filters, DashboardAnalyticsService $analytics): array
    {
        $service = app(EventOutcomeService::class);
        $ended = $analytics->query($filters)->with('eventOutcome.recorder')->get()->filter(fn ($r) =>
            in_array($r->status, ['approved', 'completed', 'no_show'], true) && ! $service->endAt($r)->isFuture());
        $recorded = $ended->filter(fn ($r) => $r->eventOutcome && $r->eventOutcome->outcome === $r->status
            && $r->eventOutcome->schedule === $service->schedule($r));
        $completed = $recorded->where('status', 'completed');
        $noShow = $recorded->where('status', 'no_show');
        $unknown = $ended->whereNotIn('id', $recorded->pluck('id'));
        $expected = $completed->sum(fn ($r) => $r->eventOutcome->expected_attendees);
        $actual = $completed->sum(fn ($r) => $r->eventOutcome->actual_attendees);
        $returns = \App\Models\EquipmentReturn::whereIn('reservation_id', $ended->pluck('id'))->with(['equipment', 'reservation'])->get();
        $returnKeys = $returns->mapWithKeys(fn ($row) => [$row->reservation_id.':'.$row->equipment_id => true]);
        $missingReturns = $ended->loadMissing('equipment')->flatMap(fn ($booking) => $booking->equipment
            ->filter(fn ($item) => (int) $item->pivot->quantity_approved > 0 && !$returnKeys->has($booking->id.':'.$item->id))
            ->map(fn ($item) => ['reservation' => $booking, 'equipment' => $item]));
        return ['eventResults' => ['eligible' => $ended->count(), 'recorded' => $recorded, 'completed' => $completed,
            'noShow' => $noShow, 'unknown' => $unknown, 'actual' => $actual, 'expected' => $expected,
            'completionRate' => $recorded->isEmpty() ? null : round($completed->count() / $recorded->count() * 100, 1),
            'noShowRate' => $recorded->isEmpty() ? null : round($noShow->count() / $recorded->count() * 100, 1),
            'coverage' => $ended->isEmpty() ? null : round($recorded->count() / $ended->count() * 100, 1),
            'attendanceRate' => $expected ? round($actual / $expected * 100, 1) : null,
            'equipmentReturns' => $returns, 'missingReturns' => $missingReturns, 'equipmentIssued' => $returns->sum('issued'),
            'equipmentGood' => $returns->sum('returned_good'),
            'equipmentUnresolved' => $returns->sum(fn ($r) => $r->damaged + $r->missing - $r->restored)]];
    }
}
