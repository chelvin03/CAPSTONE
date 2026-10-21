<?php

use App\Models\{Facility, Reservation, User};

test('admin waiting list filters records without changing queue positions and exports matching requests', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $gym = Facility::create(['facility_name' => 'MCST Gymnasium', 'capacity' => 2000, 'status' => 'available']);
    $attributes = ['facility_id' => $gym->id, 'reservation_type' => 'internal', 'event_type' => config('event-types')[0],
        'event_name' => 'Sports', 'purpose' => 'Sports', 'contact_person' => 'First Requestor', 'contact_number' => '09123456789',
        'expected_attendees' => 100, 'reservation_date' => now()->addWeek()->toDateString(),
        'start_time' => '09:00', 'end_time' => '11:00', 'status' => 'waiting_list'];
    Reservation::create([...$attributes, 'reference_number' => 'WAIT-FIRST']);
    Reservation::create([...$attributes, 'reference_number' => 'WAIT-SECOND', 'contact_person' => 'Second Requestor']);
    Reservation::create([...$attributes, 'reference_number' => 'NOT-WAITING', 'status' => 'new']);
    $this->actingAs($admin)->get(route('admin.reservations.index'))->assertOk()->assertSee('Waiting List');
    $this->get(route('admin.reservations.waiting-list', ['search' => 'Second']))->assertOk()
        ->assertSee('WAIT-SECOND')->assertDontSee('WAIT-FIRST')->assertDontSee('NOT-WAITING')
        ->assertViewHas('reservations', fn ($rows) => $rows->total() === 1 && (int) $rows->first()->queue_position === 2);
    $this->get(route('admin.reservations.waiting-list', ['date' => now()->addMonth()->toDateString()]))
        ->assertOk()->assertSee('No waiting-list requests found.');
    $response = $this->get(route('admin.reservations.waiting-list', ['search' => 'Second', 'export' => 'csv']))->assertOk()->assertDownload();
    expect($response->streamedContent())->toContain('WAIT-SECOND')->not->toContain('WAIT-FIRST', 'NOT-WAITING');
});

test('waiting list is restricted to administrators', function () {
    $this->get(route('admin.reservations.waiting-list'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create(['role' => 'staff']))->get(route('admin.reservations.waiting-list'))->assertForbidden();
});

test('reservation review actions validate waitlist and save notes with status guards', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $gym = Facility::create(['facility_name' => 'MCST Gymnasium', 'capacity' => 2000, 'status' => 'available']);
    $booking = Reservation::create(['reference_number' => 'REVIEW-ONE', 'facility_id' => $gym->id,
        'reservation_type' => 'internal', 'event_type' => config('event-types')[0], 'event_name' => 'Sports',
        'purpose' => 'Practice', 'contact_person' => 'Requestor', 'contact_number' => '09123456789',
        'expected_attendees' => 100, 'reservation_date' => now()->addWeek()->toDateString(),
        'start_time' => '09:00', 'end_time' => '11:00', 'status' => 'new']);
    $url = route('admin.reservations.review', $booking);
    $this->actingAs(User::factory()->create(['role' => 'staff']))->patch($url, ['action' => 'validate'])->assertForbidden();
    $this->actingAs($admin)->get(route('admin.reservations.show', $booking))->assertOk()
        ->assertSee('Validate Request')->assertSee('Move to Waiting List')->assertSee('Additional Information');
    $this->patch($url, ['action' => 'validate'])->assertSessionHasNoErrors();
    expect($booking->refresh()->status)->toBe('validated');
    $this->assertDatabaseHas('reservation_status_histories', ['reservation_id' => $booking->id, 'new_status' => 'validated', 'changed_by' => $admin->id]);
    $this->patch($url, ['action' => 'waitlist'])->assertSessionHasErrors('reason');
    $this->patch($url, ['action' => 'waitlist', 'reason' => 'Requested slot unavailable.'])->assertSessionHasNoErrors();
    expect($booking->refresh()->status)->toBe('waiting_list');
    $this->patch($url, ['action' => 'validate'])->assertSessionHasErrors('status');
    $this->patch($url, ['action' => 'notes', 'admin_notes' => 'Documents checked.'])->assertSessionHasNoErrors();
    expect($booking->refresh()->admin_notes)->toBe('Documents checked.')->and($booking->status)->toBe('waiting_list');
});
