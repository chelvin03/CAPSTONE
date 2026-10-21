<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

class BusinessDecisionService
{
    public function summarize(array $data, DashboardAnalyticsService $analytics): array
    {
        $filters = $data['filters'];
        $from = CarbonImmutable::parse($filters['date_from']);
        $to = CarbonImmutable::parse($filters['date_to']);
        $previousTo = $from->subDay();
        $previousFrom = $from->subDays((int) $from->diffInDays($to) + 1);
        $comparable = ! ($filters['all_time'] ?? false) && ! (($filters['year'] ?? '') === 'all' && isset($filters['month']));
        $previous = $comparable ? $analytics->query(array_merge(Arr::except($filters, ['year', 'month', 'day', 'all_time']), [
            'date_from' => $previousFrom->toDateString(), 'date_to' => $previousTo->toDateString(),
        ]))->count() : null;
        $total = $data['totalReservations'];
        $change = $previous ? round(($total - $previous) / $previous * 100, 1) : null;
        $cards = [];
        $cards[] = [
            'title' => 'Demand compared with the previous period',
            'finding' => ! $comparable ? 'A previous-period comparison is unavailable for this reporting scope.'
                : ($previous === 0 ? $total.' requests now; no requests recorded in the previous period. This is missing recorded history, not proof of zero demand. A percentage change cannot be calculated.'
                : $total.' requests versus '.$previous.' previously: '.($change > 0 ? 'up ' : ($change < 0 ? 'down ' : 'unchanged at ')).abs($change).'%.'),
            'action' => ! $total ? 'Select a period with recorded reservations before planning changes.'
                : 'Compare demand before assigning review staff and event support. Confirm upcoming bookings before changing coverage.',
            'evidence' => $comparable ? 'Previous window: '.$previousFrom->toDateString().' to '.$previousTo->toDateString().'. Same number of calendar days and the same event, status, requestor and facility filters.' : 'Select one continuous date range to compare periods.',
        ];
        $lowest = $data['weekdayDemand']->min('count');
        $quietDays = $data['weekdayDemand']->where('count', $lowest)->pluck('label')->implode(', ');
        $cards[] = [
            'title' => 'Booking patterns and maintenance planning',
            'finding' => $total ? 'Highest request count: '.$data['peakWeekday']['label'].' ('.$data['peakWeekday']['count'].' requests on each listed weekday across the selected period). Lowest count: '.$quietDays.' ('.$lowest.' requests on each listed weekday).' : 'No booking patterns can be identified without matching reservations.',
            'action' => $total ? 'Treat quieter weekdays as candidates for review only. Check Schedule and a longer recorded history before planning maintenance; zero requests does not establish recurring availability.' : 'Gather reservation history before recommending maintenance days.',
            'evidence' => 'Weekday counts describe the selected records. They are not adjusted for how often each weekday occurs in the date range and do not prove that a date is available.',
        ];
        $cards[] = [
            'title' => 'Cancellations and follow-up',
            'finding' => $total ? $data['cancelledReservations'].' of '.$total.' requests are cancelled ('.$data['cancellationRate'].'%).' : 'No cancellation rate is available without matching requests.',
            'action' => $data['cancelledReservations'] ? 'Review recorded cancellation reasons before changing reminders, scheduling rules, or requestor guidance.' : 'Continue recording cancellation reasons so future patterns can be assessed.',
            'evidence' => 'This is the current status of reservations scheduled in the selected period, not the date the cancellation happened. The records alone do not establish a cause.',
        ];
        $cards[] = [
            'title' => 'Reservation review workload',
            'finding' => $data['pendingReservations'].' requests await review; '.$data['oldPending'].' were submitted at least 7 days ago.',
            'action' => $data['oldPending'] ? 'Review the oldest pending requests first and confirm any missing requirements with the requestor.' : 'Continue reviewing new submissions and checking document requirements.',
            'evidence' => 'Pending means new or validated. The 7-day flag measures submission age as of now and is a review reminder, not an automatic rejection.',
        ];
        return ['previousTotal' => $previous ?? 0, 'previousFrom' => $previousFrom, 'previousTo' => $previousTo, 'change' => $change, 'decisionCards' => $cards, 'decisionPreviousTotal' => $previous, 'decisionChange' => $change,
            'decisionSampleNote' => $total < 5 ? 'Limited sample: fewer than 5 matching requests. Treat these findings as preliminary.' : null];
    }
}
