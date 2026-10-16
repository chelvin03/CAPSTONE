<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\User;
use App\Models\AcknowledgementReceipt;

class AcknowledgementReceiptService
{
    public function orderedTasks(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        $today = now()->toDateString();
        return $query->orderByRaw("CASE WHEN EXISTS (SELECT 1 FROM acknowledgement_tasks WHERE acknowledgement_tasks.reservation_id = reservations.id AND acknowledgement_tasks.status = 'completed') THEN 1 ELSE 0 END")
            ->orderByRaw('CASE WHEN reservation_date >= ? THEN 0 ELSE 1 END', [$today])
            ->orderByRaw('CASE WHEN reservation_date < ? THEN reservation_date ELSE NULL END DESC', [$today])
            ->orderBy('reservation_date')->orderBy('start_time')->orderBy('id');
    }

    public function ensureTask(Reservation $reservation): \App\Models\AcknowledgementTask
    {
        return \App\Models\AcknowledgementTask::firstOrCreate(['reservation_id' => $reservation->id], [
            'status' => $reservation->acknowledgementReceipt()->exists() ? 'in_progress' : 'pending',
        ]);
    }

    public function backfillTasks(): int
    {
        $created = 0;
        Reservation::where('status', 'approved')->whereDoesntHave('acknowledgementTask')->chunkById(200, function ($rows) use (&$created) {
            foreach ($rows as $row) {
                \Illuminate\Support\Facades\DB::transaction(function () use ($row, &$created) {
                    $locked = Reservation::whereKey($row->id)->lockForUpdate()->first();
                    if ($locked?->status === 'approved' && $this->ensureTask($locked)->wasRecentlyCreated) $created++;
                });
            }
        });
        return $created;
    }

    public function invalidateTask(Reservation $reservation): void
    {
        $reservation->acknowledgementTask()->update(['status' => 'pending', 'completed_at' => null, 'completed_by' => null]);
    }

    public function snapshot(Reservation $reservation): array
    {
        $reservation->load('facility', 'equipment');
        $approver = $reservation->approved_by ? User::find($reservation->approved_by) : null;
        return [
            'reference' => $reservation->reference_number, 'requestor' => $reservation->contact_person,
            'requestor_type' => $reservation->reservation_type, 'event' => $reservation->event_name,
            'event_type' => $reservation->event_type,
            'facility' => $reservation->facility?->facility_name ?? 'MCST Gymnasium',
            'date' => $reservation->reservation_date->toDateString(),
            'start' => substr($reservation->start_time, 0, 5), 'end' => substr($reservation->end_time, 0, 5),
            'participants' => (int)$reservation->expected_attendees,
            'approved_by' => $approver ? trim($approver->first_name.' '.$approver->last_name) : 'Administrator',
            'approved_at' => $reservation->approved_at ? \Carbon\CarbonImmutable::parse($reservation->approved_at)->toDateString() : null,
            'email' => $reservation->contact_email,
            'equipment' => $reservation->equipment->filter(fn ($item) => (int)$item->pivot->quantity_approved > 0)
                ->sortBy('id')->map(fn ($item) => ['name' => $item->equipment_name, 'quantity' => (int)$item->pivot->quantity_approved])->values()->all(),
        ];
    }

    public function current(AcknowledgementReceipt $receipt, Reservation $reservation): bool
    {
        $snapshot = $this->snapshot($reservation);
        // Receipts prepared before event_type was captured remain usable with their original signed links.
        if (! array_key_exists('event_type', $receipt->snapshot['booking'] ?? [])) unset($snapshot['event_type']);
        return in_array($reservation->status, ['approved', 'completed'], true)
            && ($receipt->snapshot['booking'] ?? null) === $snapshot;
    }
}
