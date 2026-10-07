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

test('approve and reject notify the requestor without a message or confirmation step', function ($action) {
    $reservation = equipmentBooking();
    $reservation->equipment()->attach($this->chairs, ['quantity_requested' => 100]);
    $data = ['action' => $action];
    if ($action === 'approve') $data['quantity_approved'] = 70;
    $this->actingAs($this->admin)->post(route('admin.reservations.equipment.review', [$reservation, $this->chairs]), $data)
        ->assertSessionHasNoErrors();
    $item = $reservation->equipment()->first();
    expect($item->pivot->status)->toBe($action === 'approve' ? 'approved' : 'rejected')
        ->and($item->pivot->quantity_approved)->toBe($action === 'approve' ? 70 : 0)
        ->and((bool) $item->pivot->requires_response)->toBeFalse()
        ->and($item->pivot->remarks)->toBeNull()
        ->and($reservation->fresh()->status)->toBe('new');
    expect($reservation->notifications()->count())->toBe(1);
    $message = $action === 'approve'
        ? 'Your equipment request for Chairs has been approved. Approved quantity: 70.'
        : 'Your equipment request for Chairs has been rejected.';
    Mail::assertSent(\App\Mail\EquipmentRequestUpdated::class, function ($mail) use ($message, $reservation) {
        return $mail->hasTo('requestor@example.com') && $mail->mailer === 'smtp'
            && str_contains($mail->render(), $message)
            && str_contains($mail->render(), $reservation->reference_number)
            && str_contains($mail->trackingUrl, 'signature=');
    });
    $this->withSession(['public_reservation_references' => [$reservation->reference_number]])
        ->get(route('reservation.track', ['reference' => $reservation->reference_number]))
        ->assertOk()->assertSee($message)->assertDontSee('Decline Equipment Request');
    $this->get(route('admin.reservations.show', $reservation))->assertOk()
        ->assertSee('Approve Equipment')->assertSee('Reject Equipment')
        ->assertDontSee('Send Reply')->assertDontSee('name="remarks"', false);
})->with(['approve', 'reject']);

test('reply actions are rejected and old confirmation offers do not block a direct decision', function () {
    $reservation = equipmentBooking();
    $reservation->equipment()->attach($this->chairs, [
        'quantity_requested' => 100, 'quantity_offered' => 50,
        'requires_response' => true, 'latest_offer_id' => (string) \Illuminate\Support\Str::uuid(),
        'status' => 'partially_available',
    ]);
    $url = route('admin.reservations.equipment.review', [$reservation, $this->chairs]);
    $this->actingAs($this->admin)->post($url, ['action' => 'reply'])->assertSessionHasErrors('action');
    expect($reservation->notifications()->count())->toBe(0);
    $this->post($url, ['action' => 'approve', 'quantity_approved' => 70])->assertSessionHasNoErrors();
    expect($reservation->equipment()->first()->pivot->quantity_approved)->toBe(70)
        ->and($reservation->equipment()->first()->pivot->latest_offer_id)->toBeNull();
});
test('staff and strangers cannot review or respond and signed email links grant scoped access', function () {
    $reservation = equipmentBooking();
    $other = equipmentBooking();
    $reservation->equipment()->attach($this->chairs, ['quantity_requested' => 100]);
    $this->actingAs(User::factory()->create(['role' => 'staff']))
        ->post(route('admin.reservations.equipment.review', [$reservation, $this->chairs]), ['action' => 'approve', 'quantity_approved' => 10])->assertForbidden();
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
    $this->post(route('admin.reservations.equipment.review', [$reservation, $this->chairs]), ['action' => 'reject'])
        ->assertSessionHasNoErrors();
    expect($reservation->equipment()->first()->pivot->status)->toBe('rejected')->and($reservation->equipment()->first()->pivot->quantity_approved)->toBe(0);
});

test('email delivery failure preserves the equipment decision and tracking notification', function () {
    $reservation = equipmentBooking();
    $reservation->equipment()->attach($this->chairs, ['quantity_requested' => 100]);
    Mail::shouldReceive('mailer')->with('smtp')->once()->andThrow(new RuntimeException('SMTP unavailable'));
    $this->actingAs($this->admin)->post(route('admin.reservations.equipment.review', [$reservation, $this->chairs]), [
        'action' => 'approve', 'quantity_approved' => 70,
    ])->assertSessionHasNoErrors();
    expect($reservation->notifications()->count())->toBe(1)
        ->and($reservation->equipment()->first()->pivot->quantity_approved)->toBe(70);
});

test('unavailable equipment and unknown inventory IDs cannot be requested', function () {
    $this->chairs->update(['status' => 'maintenance']);
    expect(fn () => app(EquipmentRequestService::class)->validateRequests('internal', [$this->chairs->id => ['quantity_requested' => 10]]))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(fn () => app(EquipmentRequestService::class)->validateRequests('internal', [999999 => ['quantity_requested' => 10]]))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('active public event page renders inventory only for verified Internal requestors', function () {
    Equipment::create(['equipment_name' => 'Speaker', 'total_quantity' => 6, 'unit' => 'pieces', 'status' => 'available']);
    foreach (['external', 'internal'] as $type) {
        $response = $this->withSession([
            'public_reservation_details' => ['contact_email' => 'requestor@example.com', 'reservation_type' => $type],
            'public_email_verified' => ['email' => 'requestor@example.com', 'verified' => true, 'expires_at' => now()->addMinutes(30)->timestamp],
        ])->get(route('reservation.create', ['step' => 3]))->assertOk();
        if ($type === 'external') {
            $response->assertDontSee('name="equipment[', false)->assertDontSee('Equipment Request (Optional');
        } else {
            $response->assertSee('name="equipment[', false)->assertSee('Chairs')->assertSee('Speaker')
                ->assertSee(':disabled="requestorType !== \'internal\'"', false);
        }
    }
});