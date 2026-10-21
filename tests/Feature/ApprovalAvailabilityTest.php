<?php

use App\Models\Facility;
use App\Models\Reservation;
use App\Models\ScheduleBlock;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    \Illuminate\Support\Facades\Storage::fake('local');
    $this->gym = Facility::create(['facility_name' => 'MCST Gymnasium', 'capacity' => 2000, 'status' => 'available']);
    $this->date = now()->addDays(5)->toDateString();
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->make = function ($status = 'new', $start = '09:00', $end = '11:00') {
        return Reservation::create(['reference_number' => 'APPROVAL-'.strtoupper(uniqid()), 'facility_id' => $this->gym->id,
            'reservation_type' => 'internal', 'event_name' => 'Practice', 'purpose' => 'Sports', 'contact_person' => 'Requestor',
            'contact_email' => 'requestor@example.com', 'contact_number' => '09123456789', 'expected_attendees' => 100,
            'reservation_date' => $this->date, 'start_time' => $start, 'end_time' => $end, 'status' => $status]);
    };
});

test('unapproved requests do not consume either availability endpoint', function ($status) {
    ($this->make)($status);
    $this->getJson(route('reservation.availability', ['date' => $this->date]))->assertOk()->assertExactJson([]);
    $this->getJson(route('reservation.availability', ['month' => substr($this->date, 0, 7)]))
        ->assertOk()->assertJsonPath('days.'.$this->date.'.status', 'available')
        ->assertJsonPath('days.'.$this->date.'.occupied', []);
})->with(['new', 'pending', 'validated', 'waiting_list', 'pencil', 'cancelled', 'rejected']);

test('pending slots accept new requests and occupied slots automatically create waiting requests', function () {
    $payload = ['reservation_type' => 'internal', 'event_type' => config('event-types')[0], 'purpose' => 'Sports', 'contact_person' => 'Requestor',
        'contact_email' => 'requestor@example.com', 'contact_number' => '09123456789', 'expected_attendees' => 100,
        'reservation_date' => $this->date, 'start_time' => '09:00', 'end_time' => '11:00', 'agreement' => 1, 'agreement_accepted' => 1,
        'permit' => \Illuminate\Http\UploadedFile::fake()->create('letter.pdf', 10, 'application/pdf')];
    $proof = ['public_email_verified' => ['email' => 'requestor@example.com', 'verified' => true, 'expires_at' => now()->addMinutes(30)->timestamp]];
    $this->withSession($proof)->post(route('reservation.store'), $payload)->assertSessionHasNoErrors();
    $this->withSession($proof)->post(route('reservation.store'), $payload)->assertSessionHasNoErrors();
    expect(Reservation::count())->toBe(2);
    $first = Reservation::first();
    $this->actingAs($this->admin)->patch(route('admin.reservations.approve', $first))->assertSessionHasNoErrors();
    $this->patch(route('admin.reservations.approve', Reservation::latest('id')->first()))->assertSessionHasErrors('reservation_date');
    $this->getJson(route('reservation.availability', ['date' => $this->date]))->assertOk()->assertJsonCount(1);
    $this->withSession($proof)->post(route('reservation.store'), $payload)->assertSessionHasNoErrors();
    expect(Reservation::latest('id')->first()->status)->toBe('waiting_list');
    expect(Reservation::where('status', 'approved')->count())->toBe(1);
});

test('approval respects approved cleaning buffers but ignores other pending requests', function () {
    ($this->make)('approved');
    $insideBuffer = ($this->make)('new', '11:00', '12:00');
    $outsideBuffer = ($this->make)('new', '11:30', '12:30');
    ($this->make)('validated', '11:30', '12:30');
    $this->actingAs($this->admin)->patch(route('admin.reservations.approve', $insideBuffer))->assertSessionHasErrors('reservation_date');
    $this->patch(route('admin.reservations.approve', $outsideBuffer))->assertSessionHasNoErrors();
    expect($outsideBuffer->refresh()->status)->toBe('approved');
});

test('approval cannot bypass an administrator blackout', function () {
    $request = ($this->make)();
    ScheduleBlock::create(['facility_id' => $this->gym->id, 'created_by' => $this->admin->id,
        'starts_on' => $this->date, 'ends_on' => $this->date, 'title' => 'Closure']);
    $this->actingAs($this->admin)->patch(route('admin.reservations.approve', $request))->assertSessionHasErrors('reservation_date');
    expect($request->refresh()->status)->toBe('new');
});
