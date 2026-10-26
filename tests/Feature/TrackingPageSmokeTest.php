<?php

use App\Models\Facility;
use App\Models\Reservation;

test('tracking page renders after submission including midnight and waiting list reservations', function (string $status) {
    $gym = Facility::create(['facility_name' => 'MCST Main Gymnasium', 'capacity' => 2000, 'status' => 'available']);
    $booking = Reservation::create([
        'reference_number' => 'R42-A', 'facility_id' => $gym->id, 'reservation_type' => 'internal',
        'event_name' => 'School Event', 'event_type' => 'School Activities', 'purpose' => 'Activity',
        'contact_person' => 'Requestor', 'contact_number' => '09123456789', 'contact_email' => 'requestor@example.com',
        'expected_attendees' => 100, 'reservation_date' => now()->addDays(5)->toDateString(),
        'start_time' => '07:00', 'end_time' => '24:00', 'status' => $status,
    ]);
    $this->withSession(['reservation_submitted' => $booking->reference_number, 'public_reservation_references' => [$booking->reference_number]])
        ->get(route('reservation.track', ['reference' => $booking->reference_number]))->assertOk()
        ->assertSee('Reservation Submitted Successfully')->assertSee('R42-A')->assertSee('School Event');
    $this->getJson(route('reservation.track', ['reference' => $booking->reference_number]))->assertOk()->assertJsonPath('status', $status);
})->with(['new', 'waiting_list']);
