<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\User;
use App\Models\AcknowledgementReceipt;

class AcknowledgementReceiptService
{
    public function snapshot(Reservation $reservation): array
    {
        $reservation->load('facility', 'equipment');
        $approver = $reservation->approved_by ? User::find($reservation->approved_by) : null;
        return [
            'reference' => $reservation->reference_number, 'requestor' => $reservation->contact_person,
            'requestor_type' => $reservation->reservation_type, 'event' => $reservation->event_name,
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
        return in_array($reservation->status, ['approved', 'completed'], true)
            && ($receipt->snapshot['booking'] ?? null) === $this->snapshot($reservation);
    }
}
