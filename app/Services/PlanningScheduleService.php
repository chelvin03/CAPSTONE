<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Facility;
use App\Models\Reservation;
use App\Models\ScheduleBlock;
use Carbon\CarbonImmutable;

class PlanningScheduleService
{
    public const TASKS = [
        'venue' => 'Confirm venue schedule',
        'seating' => 'Prepare chairs and tables',
        'equipment' => 'Verify requested equipment',
        'inspection' => 'Conduct facility inspection',
    ];

    public const STATUSES = ['approved', 'new', 'validated', 'pending', 'waiting_list', 'pencil'];

    public function snapshot(Reservation $reservation): string
    {
        return hash('sha256', json_encode([
            $reservation->only(['facility_id', 'reservation_date', 'start_time', 'end_time', 'status', 'event_name', 'event_type', 'expected_attendees', 'requested_equipment', 'requested_equipment_quantity', 'setup_time', 'cleanup_time']),
            $reservation->equipment->sortBy('id')->map(fn ($item) => [$item->id, $item->equipment_name, $item->pivot->quantity_requested, $item->pivot->quantity_approved, $item->pivot->status, $item->pivot->requestor_response])->values()->all(),
        ], JSON_THROW_ON_ERROR));
    }

    public function summarize(?int $selectedId = null): array
    {
        $today = CarbonImmutable::today();
        $until = $today->addDays(3);
        $facilities = Facility::gym()->get();
        $records = Reservation::whereIn('facility_id', $facilities->pluck('id'))->whereIn('status', self::STATUSES)
            ->where('reservation_date', '>=', $today->toDateString())->where('reservation_date', '<', $until->addDay()->toDateString())
            ->with(['facility', 'equipment', 'documents', 'preparations.updater', 'acknowledgementTask', 'acknowledgementReceipt'])
            ->orderBy('reservation_date')->orderBy('start_time')->orderBy('id')->get();
        $blocks = ScheduleBlock::whereDate('starts_on', '<=', $until)->whereDate('ends_on', '>=', $today)->get();
        $scheduleDays = [];
        foreach ([$today->startOfMonth(), $until->startOfMonth()] as $month) {
            if (! isset($scheduleDays[$month->toDateString()])) {
                $scheduleDays += app(AdminScheduleService::class)->month($month, $facilities, $records, $blocks)['days'];
            }
        }
        $activities = $records->map(function ($reservation) use ($scheduleDays) {
            $snapshot = $this->snapshot($reservation);
            $tasks = collect(self::TASKS)->map(function ($label, $key) use ($reservation, $snapshot) {
                $row = $reservation->preparations->firstWhere('task', $key);

                return ['key' => $key, 'label' => $label, 'completed' => $row && $row->completed && hash_equals($snapshot, $row->snapshot_hash),
                    'stale' => $row && $row->completed && ! hash_equals($snapshot, $row->snapshot_hash),
                    'updatedBy' => $row?->updater?->full_name, 'updatedAt' => $row?->updated_at];
            });
            $reasons = [];
            if ($reservation->status !== 'approved') {
                $reasons[] = 'Reservation awaits administrator confirmation; it is not a confirmed booking.';
            }
            foreach ($scheduleDays[$reservation->reservation_date->toDateString()]['issues'] ?? [] as $issue) {
                if (collect($issue['bookings'])->contains('reference', $reservation->reference_number)) {
                    $reasons[] = $issue['title'];
                }
            }
            if ($reservation->facility?->status !== 'available') {
                $reasons[] = 'Facility is currently unavailable.';
            }
            if ($reservation->user_id === null && ! $reservation->documents->contains(fn ($d) => $d->document_type === 'permit' && filled($d->file_path))) {
                $reasons[] = 'Supporting approval letter needs review.';
            }
            if ($reservation->equipment->contains(fn ($item) => $item->pivot->quantity_requested > 0 && ($item->pivot->status !== 'approved' || $item->pivot->quantity_approved < 1))) {
                $reasons[] = 'Equipment requests need allocation or administrator review.';
            }
            if ($reservation->equipment->contains(fn ($item) => $item->pivot->quantity_approved > 0 && app(EquipmentRequestService::class)->available($item, $reservation) < $item->pivot->quantity_approved)) {
                $reasons[] = 'Approved equipment allocation exceeds current usable availability.';
            }
            if (filled($reservation->requested_equipment) && ! $tasks['equipment']['completed']) {
                $reasons[] = 'Custom equipment requirements need administrator review.';
            }
            $start = CarbonImmutable::parse($reservation->reservation_date->toDateString().' '.$reservation->start_time);
            if ($tasks->contains('stale', true)) {
                $reasons[] = 'Activity details changed; recheck the preparation checklist.';
            }
            if ($start->lte(CarbonImmutable::now()->addDay()) && $tasks->contains('completed', false)) {
                $reasons[] = 'Preparation checklist is incomplete for an activity starting within 24 hours or already started.';
            }
            $ready = $reservation->status === 'approved' && $tasks->every('completed', true) && empty($reasons);
            $receiptLabel = $reservation->acknowledgementReceipt && ! app(AcknowledgementReceiptService::class)->current($reservation->acknowledgementReceipt, $reservation)
                ? 'Needs regeneration' : ucfirst(str_replace('_', ' ', $reservation->acknowledgementTask?->status ?? 'pending'));

            return ['reservation' => $reservation, 'snapshot' => $snapshot, 'tasks' => $tasks, 'reasons' => array_values(array_unique($reasons)),
                'ready' => $ready, 'receiptLabel' => $receiptLabel, 'preparationLabel' => $ready ? 'Ready' : ($reservation->status !== 'approved' ? 'Pending confirmation' : 'Preparation needed')];
        });
        $selected = $activities->first(fn ($row) => $row['reservation']->id === $selectedId) ?? $activities->first(fn ($row) => ! $row['ready']) ?? $activities->first();
        $days = collect(range(0, 3))->map(function ($offset) use ($today, $activities) {
            $date = $today->addDays($offset);

            return ['date' => $date, 'label' => match ($offset) {
                0 => 'Today', 1 => 'Tomorrow', default => $date->format('l')
            },
                'activities' => $activities->filter(fn ($row) => $row['reservation']->reservation_date->toDateString() === $date->toDateString())];
        });

        return compact('today', 'until', 'activities', 'selected', 'days') + [
            'total' => $activities->count(), 'readyCount' => $activities->where('ready', true)->count(),
            'preparationCount' => $activities->filter(fn ($a) => $a['tasks']->contains('completed', false))->count(),
            'attention' => $activities->filter(fn ($row) => ! empty($row['reasons'])),
        ];
    }
}
