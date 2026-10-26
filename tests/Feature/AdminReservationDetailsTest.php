<?php

use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;

test('reservation overview keeps details in cards and separates equipment and attendance while preserving admin actions', function (string $status) {
    $admin = User::factory()->create(['role' => 'admin']);
    $gym = Facility::create(['facility_name' => 'MCST Main Gymnasium', 'capacity' => 2000, 'status' => 'available']);
    $reservation = Reservation::create([
        'reference_number' => 'DETAILS-'.$status, 'facility_id' => $gym->id, 'user_id' => $admin->id,
        'reservation_type' => 'internal', 'event_name' => 'Halloween', 'event_type' => 'School Event',
        'purpose' => 'School celebration', 'contact_person' => 'Requestor', 'contact_email' => 'requestor@example.com',
        'contact_number' => '09123456789', 'expected_attendees' => 1976, 'reservation_date' => '2026-11-01',
        'start_time' => '07:00', 'end_time' => '24:00', 'status' => $status,
    ]);
    $response = $this->actingAs($admin)->get(route('admin.reservations.show', $reservation))->assertOk()
        ->assertSee('17 hours 0 minutes')->assertSee('98.8%')->assertSee('MCST Main Gymnasium')
        ->assertSee('Admin Actions')->assertSee('Attendance &amp; Outcome', false)->assertSee('History &amp; Notes', false);
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " detail-summary ")]')->length)->toBe(0);
    expect($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " detail-overview-layout ")]//*[@id="event-outcome"]')->length)->toBe(0);
    expect($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " detail-overview-layout ")]//*[contains(@class,"equipment-review")]')->length)->toBe(0);
    expect($xpath->query('//*[@id="event-outcome"]')->length)->toBe(1);
    expect($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " equipment-review ")]')->length)->toBe(1);
    if ($status === 'new') {
        $response->assertSee('Validate Request')->assertSee('Approve Reservation')->assertSee('Reject Reservation');
    }
})->with(['new', 'approved']);
