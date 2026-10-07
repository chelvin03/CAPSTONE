<?php

use App\Models\Equipment;
use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;
use App\Services\EquipmentRequestService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

function equipmentBooking(array $overrides = []): Reservation
{
    $facility = Facility::firstOrCreate(['facility_name' => 'Equipment Gym'], ['capacity' => 2000, 'status' => 'available']);
    return Reservation::create($overrides + [
        'reference_number' => 'EQUIP-'.\Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(8)), 'facility_id' => $facility->id,
        'reservation_type' => 'internal', 'event_name' => 'Equipment Event', 'purpose' => 'School activity',
        'contact_person' => 'Equipment Requestor', 'contact_email' => 'requestor@example.com', 'contact_number' => '09123456789',
        'expected_attendees' => 100, 'reservation_date' => now()->addDays(5)->toDateString(),
        'start_time' => '08:00', 'end_time' => '12:00', 'status' => 'new',
    ]);
}

beforeEach(function () {
    Mail::fake();
    Storage::fake('local');
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->chairs = Equipment::create(['equipment_name' => 'Chairs', 'total_quantity' => 150, 'unit' => 'pieces', 'status' => 'available']);
});

test('only internal public requestors can store inventory requests and external requests store nothing', function () {
    $facility = Facility::create(['facility_name' => 'Public Equipment Gym', 'capacity' => 2000, 'status' => 'available']);
    $data = ['facility_id' => $facility->id, 'reservation_type' => 'external', 'event_name' => 'Equipment Event',
        'purpose' => 'School activity', 'contact_person' => 'Equipment Requestor', 'contact_email' => 'requestor@example.com',
        'contact_number' => '09123456789', 'expected_attendees' => 100, 'reservation_date' => now()->addDays(5)->toDateString(),
        'start_time' => '08:00', 'end_time' => '12:00', 'agreement' => '1', 'agreement_accepted' => '1',
        'equipment' => [$this->chairs->id => ['quantity_requested' => 200]],
        'permit' => UploadedFile::fake()->create('permit.pdf', 20, 'application/pdf')];
    $this->withSession(['public_email_verified' => ['email' => 'requestor@example.com', 'verified' => true, 'expires_at' => now()->addMinutes(30)->timestamp]])
        ->post(route('reservation.store'), $data)->assertSessionHasErrors('equipment');
    $this->assertDatabaseCount('reservations', 0);
    expect(Storage::disk('local')->allFiles())->toBeEmpty();
    Mail::assertNothingSent();
    $this->post(route('reservation.store'), array_replace($data, ['reservation_type' => 'internal']))->assertSessionHasNoErrors();
    $item = Reservation::sole()->equipment()->firstOrFail();
    expect($item->pivot->quantity_requested)->toBe(200)->and($item->pivot->quantity_approved)->toBeNull()->and($item->pivot->status)->toBe('pending');
});

test('public inventory input rejects invalid quantities unavailable stock ids and injected approvals', function ($row) {
    $facility = Facility::create(['facility_name' => 'Invalid Equipment Gym', 'capacity' => 2000, 'status' => 'available']);
    $data = ['facility_id' => $facility->id, 'reservation_type' => 'internal', 'event_name' => 'Equipment Event', 'purpose' => 'School activity',
        'contact_person' => 'Equipment Requestor', 'contact_email' => 'requestor@example.com', 'contact_number' => '09123456789',
        'expected_attendees' => 100, 'reservation_date' => now()->addDays(5)->toDateString(), 'start_time' => '08:00', 'end_time' => '12:00',
        'agreement' => '1', 'agreement_accepted' => '1', 'equipment' => [$this->chairs->id => $row],
        'permit' => UploadedFile::fake()->create('permit.pdf', 20, 'application/pdf')];
    $this->withSession(['public_email_verified' => ['email' => 'requestor@example.com', 'verified' => true, 'expires_at' => now()->addMinutes(30)->timestamp]])
        ->post(route('reservation.store'), $data)->assertSessionHasErrors();
    $this->assertDatabaseCount('reservations', 0);
})->with([[['quantity_requested' => -1]], [['quantity_requested' => 0]], [['quantity_requested' => '1.5']], [['quantity_requested' => 10, 'quantity_approved' => 10]]]);

test('verified external session cannot be retyped internally at submission to bypass equipment restriction', function () {
    $facility = Facility::create(['facility_name' => 'Retyped Gym', 'capacity' => 2000, 'status' => 'available']);
    $this->withSession(['public_reservation_details' => ['reservation_type' => 'external'],
        'public_email_verified' => ['email' => 'requestor@example.com', 'verified' => true, 'expires_at' => now()->addMinutes(30)->timestamp]])
        ->post(route('reservation.store'), ['facility_id' => $facility->id, 'reservation_type' => 'internal', 'event_name' => 'Equipment Event',
            'purpose' => 'School activity', 'contact_person' => 'Requestor', 'contact_email' => 'requestor@example.com', 'contact_number' => '09123456789',
            'expected_attendees' => 100, 'reservation_date' => now()->addDays(5)->toDateString(), 'start_time' => '08:00', 'end_time' => '12:00',
            'agreement' => '1', 'agreement_accepted' => '1', 'equipment' => [$this->chairs->id => ['quantity_requested' => 100]],
            'permit' => UploadedFile::fake()->create('permit.pdf', 20, 'application/pdf')])->assertSessionHasErrors('reservation_type');
    $this->assertDatabaseCount('reservations', 0);
});

test('overlapping approvals cannot exceed available equipment and nonoverlapping stock is reusable', function () {
    $first = equipmentBooking(['status' => 'approved']);
    $first->equipment()->attach($this->chairs, ['quantity_requested' => 100, 'quantity_approved' => 100, 'status' => 'approved']);
    $second = equipmentBooking(['status' => 'approved']);
    $second->equipment()->attach($this->chairs, ['quantity_requested' => 80]);
    expect(app(EquipmentRequestService::class)->available($this->chairs, $second))->toBe(50);
    $url = route('admin.reservations.equipment.review', [$second, $this->chairs]);
    $this->actingAs($this->admin)->post($url, ['action' => 'approve', 'quantity_approved' => 80])->assertSessionHasErrors('quantity_approved');
    expect($second->equipment()->first()->pivot->quantity_approved)->toBeNull();
    $this->post($url, ['action' => 'approve', 'quantity_approved' => 50])->assertSessionHasNoErrors();
    expect($second->equipment()->first()->pivot->quantity_approved)->toBe(50);
    $later = equipmentBooking(['start_time' => '12:00', 'end_time' => '15:00']);
    expect(app(EquipmentRequestService::class)->available($this->chairs, $later))->toBe(150);
});

test('availability uses peak simultaneous quantities rather than adding disjoint allocations', function () {
    foreach ([['08:00','10:00'], ['10:00','12:00']] as [$start,$end]) {
        $booking = equipmentBooking(['status' => 'approved', 'start_time' => $start, 'end_time' => $end]);
        $booking->equipment()->attach($this->chairs, ['quantity_requested' => 100, 'quantity_approved' => 100]);
    }
    expect(app(EquipmentRequestService::class)->available($this->chairs, equipmentBooking()))->toBe(50);
});

test('reply confirmation offer requestor response and final approval preserve the reservation workflow', function () {
    $reservation = equipmentBooking();
    $reservation->equipment()->attach($this->chairs, ['quantity_requested' => 100]);
    $review = route('admin.reservations.equipment.review', [$reservation, $this->chairs]);
    $this->actingAs($this->admin)->post($review, ['action' => 'reply', 'quantity_offered' => 70, 'requires_response' => 1,
        'remarks' => 'Only 70 chairs are available. Please confirm.'])->assertSessionHasNoErrors();
    $item = $reservation->equipment()->first();
    expect($item->pivot->status)->toBe('partially_available')->and($item->pivot->reply_sent_at)->not->toBeNull()
        ->and($item->pivot->quantity_approved)->toBeNull();
    expect($reservation->notifications()->count())->toBe(1);
    Mail::assertSent(\App\Mail\EquipmentRequestUpdated::class, function ($mail) use ($reservation) {
        return $mail->hasTo('requestor@example.com') && $mail->mailer === 'smtp'
            && str_contains($mail->render(), $reservation->reference_number)
            && str_contains($mail->trackingUrl, 'signature=');
    });
    $this->post($review, ['action' => 'approve', 'quantity_approved' => 70])->assertSessionHasErrors('equipment');
    $this->withSession(['public_reservation_references' => [$reservation->reference_number]])
        ->get(route('reservation.track', ['reference' => $reservation->reference_number]))->assertOk()
        ->assertSee('Only 70 chairs are available. Please confirm.')->assertSee('Accept 70 Chairs');
    $response = route('reservation.equipment.respond', [$reservation, $this->chairs]);
    $this->post($response, ['response' => 'accepted', 'offer_id' => $item->pivot->latest_offer_id])->assertSessionHasNoErrors();
    expect($reservation->equipment()->first()->pivot->status)->toBe('accepted_by_requestor')
        ->and($reservation->equipment()->first()->pivot->quantity_approved)->toBeNull();
    expect($this->admin->notifications()->count())->toBe(1);
    $this->post($response, ['response' => 'declined', 'offer_id' => $item->pivot->latest_offer_id])->assertSessionHasErrors('equipment');
    $this->post($review, ['action' => 'approve', 'quantity_approved' => 70])->assertSessionHasNoErrors();
    expect($reservation->equipment()->first()->pivot->quantity_approved)->toBe(70)->and($reservation->fresh()->status)->toBe('new');
    $this->get(route('admin.reservations.show', $reservation))->assertOk()->assertSee('accepted the offer of 70 Chairs');
    $this->assertDatabaseHas('audit_logs', ['action' => 'equipment.requestor_responded']);
});

test('requestor decline and stale offers are recorded and cannot be approved', function () {
    $reservation = equipmentBooking();
    $reservation->equipment()->attach($this->chairs, ['quantity_requested' => 100]);
    $url = route('admin.reservations.equipment.review', [$reservation, $this->chairs]);
    $offer = ['action' => 'reply', 'quantity_offered' => 70, 'requires_response' => 1, 'remarks' => 'Confirm quantity.'];
    $this->actingAs($this->admin)->post($url, $offer)->assertSessionHasNoErrors();
    $old = $reservation->equipment()->first()->pivot->latest_offer_id;
    $this->post($url, $offer)->assertSessionHasNoErrors();
    $current = $reservation->equipment()->first()->pivot->latest_offer_id;
    $response = route('reservation.equipment.respond', [$reservation, $this->chairs]);
    $this->withSession(['public_reservation_references' => [$reservation->reference_number]])
        ->post($response, ['response' => 'accepted', 'offer_id' => $old])->assertSessionHasErrors('equipment');
    $this->post($response, ['response' => 'declined', 'offer_id' => $current])->assertSessionHasNoErrors();
    expect($reservation->equipment()->first()->pivot->status)->toBe('declined_by_requestor');
    $this->post($url, ['action' => 'approve', 'quantity_approved' => 70])->assertSessionHasErrors('equipment');
    expect($reservation->notifications()->count())->toBe(3);
});

test('staff and strangers cannot review or respond and signed email links grant scoped access', function () {
    $reservation = equipmentBooking();
    $other = equipmentBooking();
    $reservation->equipment()->attach($this->chairs, ['quantity_requested' => 100]);
    $this->actingAs(User::factory()->create(['role' => 'staff']))
        ->post(route('admin.reservations.equipment.review', [$reservation, $this->chairs]), ['action' => 'approve', 'quantity_approved' => 10])->assertForbidden();
    $this->get(route('staff.reservations.show', $reservation))->assertOk()->assertSee('Chairs')->assertDontSee('Approve Equipment');
    auth()->logout();
    $this->withSession(['public_reservation_references' => []])
        ->post(route('reservation.equipment.respond', [$reservation, $this->chairs]), ['response' => 'accepted', 'offer_id' => (string) \Illuminate\Support\Str::uuid()])->assertForbidden();
    $this->get(route('reservation.equipment.access', $reservation))->assertForbidden();
    $this->get(URL::temporarySignedRoute('reservation.equipment.access', now()->addHour(), ['reservation' => $reservation->id]))
        ->assertRedirect(route('reservation.track', ['reference' => $reservation->reference_number]));
    $this->post(route('reservation.equipment.respond', [$other, $this->chairs]), ['response' => 'accepted', 'offer_id' => (string) \Illuminate\Support\Str::uuid()])->assertForbidden();
});

test('reservation approval and schedule editing recheck equipment and rollback overbooking', function () {
    $occupied = equipmentBooking(['status' => 'approved']);
    $occupied->equipment()->attach($this->chairs, ['quantity_requested' => 100, 'quantity_approved' => 100]);
    $pending = equipmentBooking(['start_time' => '12:00', 'end_time' => '15:00']);
    $pending->equipment()->attach($this->chairs, ['quantity_requested' => 80, 'quantity_approved' => 80, 'status' => 'approved']);
    $this->actingAs($this->admin)->patch(route('admin.reservations.approve', $pending))->assertSessionHasNoErrors();
    $this->patch(route('admin.reservations.reschedule', $pending), [
        'facility_id' => $pending->facility_id, 'reservation_date' => $pending->reservation_date->toDateString(),
        'start_time' => '09:00', 'end_time' => '13:00', 'reason' => 'Move schedule',
        'force_assign' => 1, 'conflict_resolution' => 'waiting_list',
    ])->assertSessionHasNoErrors();
    // Forced reassignment releases the occupied reservation's stock, preserving the existing workflow.
    expect($occupied->fresh()->status)->toBe('waiting_list');
    $this->patch(route('admin.reservations.approve', $occupied))->assertSessionHasErrors('equipment');
    expect($occupied->fresh()->status)->toBe('waiting_list');
    $anotherFacility = Facility::create(['facility_name' => 'Other Equipment Gym', 'capacity' => 2000, 'status' => 'available']);
    $another = equipmentBooking(['facility_id' => $anotherFacility->id, 'status' => 'approved', 'start_time' => '16:00', 'end_time' => '18:00']);
    $another->equipment()->attach($this->chairs, ['quantity_requested' => 100, 'quantity_approved' => 100, 'status' => 'approved']);
    $this->put(route('admin.reservations.update', $another), [
        'facility_id' => $anotherFacility->id, 'reservation_type' => 'internal', 'event_name' => 'Equipment Event', 'purpose' => 'School activity',
        'contact_person' => 'Requestor', 'contact_number' => '09123456789', 'expected_attendees' => 100,
        'reservation_date' => $another->reservation_date->toDateString(), 'start_time' => '09:00', 'end_time' => '11:00',
    ])->assertSessionHasErrors('equipment');
    expect(substr($another->fresh()->start_time, 0, 5))->toBe('16:00');
});

test('external admin requests cannot bypass the restriction and rejection leaves stock unallocated', function () {
    $reservation = equipmentBooking(['reservation_type' => 'external']);
    $reservation->equipment()->attach($this->chairs, ['quantity_requested' => 10]);
    $this->actingAs($this->admin)->post(route('admin.reservations.equipment.review', [$reservation, $this->chairs]), ['action' => 'approve', 'quantity_approved' => 10])
        ->assertSessionHasErrors('equipment');
    $reservation->update(['reservation_type' => 'internal']);
    $this->post(route('admin.reservations.equipment.review', [$reservation, $this->chairs]), ['action' => 'reject', 'remarks' => 'Equipment unavailable.'])
        ->assertSessionHasNoErrors();
    expect($reservation->equipment()->first()->pivot->status)->toBe('rejected')->and($reservation->equipment()->first()->pivot->quantity_approved)->toBe(0);
});

test('email delivery failure preserves the admin reply and tracking notification', function () {
    $reservation = equipmentBooking();
    $reservation->equipment()->attach($this->chairs, ['quantity_requested' => 100]);
    Mail::shouldReceive('mailer')->with('smtp')->once()->andThrow(new RuntimeException('SMTP unavailable'));
    $this->actingAs($this->admin)->post(route('admin.reservations.equipment.review', [$reservation, $this->chairs]), [
        'action' => 'reply', 'quantity_offered' => 70, 'requires_response' => 1, 'remarks' => 'Only 70 available.',
    ])->assertSessionHasNoErrors();
    expect($reservation->notifications()->count())->toBe(1)
        ->and($reservation->equipment()->first()->pivot->remarks)->toBe('Only 70 available.');
});

test('unavailable equipment and unknown inventory IDs cannot be requested', function () {
    $this->chairs->update(['status' => 'maintenance']);
    expect(fn () => app(EquipmentRequestService::class)->validateRequests('internal', [$this->chairs->id => ['quantity_requested' => 10]]))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(fn () => app(EquipmentRequestService::class)->validateRequests('internal', [999999 => ['quantity_requested' => 10]]))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});
