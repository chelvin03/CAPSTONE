<?php

use App\Models\Facility;
use App\Models\Reservation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

function validPublicReservationData(Facility $facility): array
{
    test()->withSession(['public_email_verified' => ['email' => 'juan@example.com', 'verified' => true, 'expires_at' => now()->addMinutes(30)->timestamp]]);

    return [
        'facility_id' => $facility->id,
        'reservation_type' => 'community',
        'event_name' => 'Community Sports Day',
        'event_type' => 'Sports',
        'purpose' => 'Community recreation',
        'contact_person' => 'Juan Dela Cruz',
        'contact_email' => 'juan@example.com',
        'contact_number' => '09123456789',
        'expected_attendees' => 25,
        'reservation_date' => now()->addDays(3)->format('Y-m-d'),
        'start_time' => '09:00',
        'end_time' => '11:00',
        'agreement' => '1',
        'email_verified' => '1',
    ];
}

test('a public reservation stores its required permit', function () {
    Storage::fake('local');
    Mail::fake();

    $facility = Facility::create([
        'facility_name' => 'Main Gym',
        'capacity' => 100,
        'status' => 'available',
    ]);

    $data = validPublicReservationData($facility);
    $data['permit'] = UploadedFile::fake()->create(
        'gym-permit.pdf',
        250,
        'application/pdf'
    );
    $data['requested_equipment'] = 'Volleyball net';
    $data['requested_equipment_quantity'] = 2;

    $response = $this->post(route('reservation.store'), $data);

    $reservation = Reservation::firstOrFail();
    $document = $reservation->documents()->firstOrFail();
    Mail::assertSent(\App\Mail\ReservationSubmitted::class, function ($mail) use ($reservation) {
        return $mail->hasTo($reservation->contact_email)
            && $mail->mailer === 'smtp'
            && $mail->trackingUrl === route('reservation.track', ['reference' => $reservation->reference_number])
            && str_contains($mail->render(), $reservation->reference_number)
            && str_contains($mail->render(), 'Awaiting administrator review');
    });
    $response->assertSessionHas('reservation_email_sent', true);
    $this->get(route('reservation.success', $reservation->reference_number))->assertOk()
        ->assertSee('Reservation Request Received')->assertSee('Track Reservation');

    $response->assertRedirect(
        route('reservation.success', $reservation->reference_number)
    );
    expect($document->document_type)->toBe('permit')
        ->and($document->original_filename)->toBe('gym-permit.pdf')
        ->and($document->uploaded_by)->toBeNull()
        ->and($reservation->requested_equipment)->toBe('Volleyball net')
        ->and($reservation->requested_equipment_quantity)->toBe(2);
    Storage::disk('local')->assertExists($document->file_path);
    $this->get(route('reservation.track', ['reference' => $reservation->reference_number]))
        ->assertOk()
        ->assertSee($reservation->reference_number)
        ->assertSee('Community Sports Day');
});

test('public reservations require at least three days notice', function () {
    Storage::fake('local');
    config(['gym.reservation.minimum_notice_days' => 3]);

    $facility = Facility::create([
        'facility_name' => 'Advance Window Gym',
        'capacity' => 100,
        'status' => 'available',
    ]);

    $data = validPublicReservationData($facility);
    $data['reservation_date'] = now()->addDays(2)->toDateString();
    $data['permit'] = UploadedFile::fake()->create('permit.pdf', 50, 'application/pdf');

    $this->post(route('reservation.store'), $data)
        ->assertSessionHasErrors('reservation_date');
});

test('a public reservation requires an exact eleven digit contact number', function () {
    Storage::fake('local');
    $facility = Facility::create(['facility_name' => 'Contact Gym', 'capacity' => 100, 'status' => 'available']);
    $data = validPublicReservationData($facility);
    $data['contact_number'] = '0912345678';
    $data['permit'] = UploadedFile::fake()->create('permit.pdf', 50, 'application/pdf');
    $this->post(route('reservation.store'), $data)->assertSessionHasErrors('contact_number');
});

test('a public reservation requires a valid permit', function () {
    Storage::fake('local');

    $facility = Facility::create([
        'facility_name' => 'Main Gym',
        'capacity' => 100,
        'status' => 'available',
    ]);

    $this->post(route('reservation.store'), validPublicReservationData($facility))
        ->assertSessionHasErrors('permit');

    $data = validPublicReservationData($facility);
    $data['permit'] = UploadedFile::fake()->create(
        'malware.exe',
        10,
        'application/octet-stream'
    );

    $this->post(route('reservation.store'), $data)
        ->assertSessionHasErrors('permit');
});

test('a public reservation requires a valid email format without otp verification', function () {
    Storage::fake('local');
    $facility = Facility::create([
        'facility_name' => 'Verification Gym',
        'capacity' => 100,
        'status' => 'available',
    ]);
    $data = validPublicReservationData($facility);
    $data['contact_email'] = 'not-an-email';
    $data['permit'] = UploadedFile::fake()->create('permit.pdf', 50, 'application/pdf');

    $this->post(route('reservation.store'), $data)
        ->assertSessionHasErrors('contact_email');

    $this->assertDatabaseCount('reservations', 0);
});

test('public reservations require the contact email to be verified before submission', function () {
    Storage::fake('local');
    $facility = Facility::create([
        'facility_name' => 'Verified Email Gym',
        'capacity' => 100,
        'status' => 'available',
    ]);

    $data = validPublicReservationData($facility);
    $data['contact_email'] = 'unverified@example.com';
    $data['permit'] = UploadedFile::fake()->create('permit.pdf', 50, 'application/pdf');

    $this->post(route('reservation.store'), $data)
        ->assertSessionHasErrors('contact_email');

    $this->assertDatabaseCount('reservations', 0);
});


test('public submission ignores forged browser verification and consumes real proof', function () {
    Storage::fake('local');
    Mail::fake();
    $facility = Facility::create(['facility_name' => 'OTP Gym', 'capacity' => 100, 'status' => 'available']);
    $data = validPublicReservationData($facility);
    $data['permit'] = UploadedFile::fake()->create('permit.pdf', 50, 'application/pdf');
    $this->withSession(['public_email_verified' => null])->post(route('reservation.store'), $data)
        ->assertSessionHasErrors('contact_email');
    $this->assertDatabaseCount('reservations', 0);

    $this->postJson('/reserve/send-code', ['email' => 'juan@example.com', 'contact_person' => 'Juan Dela Cruz', 'contact_number' => '09123456789', 'reservation_type' => 'community'])->assertOk();
    $code = Mail::sent(\App\Mail\PublicRegistrationCode::class)->sole()->code;
    $this->postJson('/reserve/verify-code', ['email' => 'juan@example.com', 'code' => $code])->assertOk();
    unset($data['email_verified']);
    $this->post(route('reservation.store'), $data)->assertSessionHasNoErrors()
        ->assertSessionMissing('public_email_verified')->assertSessionMissing('public_email_challenge')
        ->assertSessionMissing('public_reservation_details')
        ->assertRedirect(route('reservation.success', Reservation::sole()->reference_number));
    expect(Reservation::sole()->user_id)->toBeNull();
    $this->assertGuest();
    $this->get(route('reservation.create', ['step' => 3]))->assertRedirect(route('reservation.create'));
});

test('confirmation mail failure preserves the reservation and tracking page', function () {
    Storage::fake('local');
    Mail::shouldReceive('mailer')->with('smtp')->once()->andThrow(new RuntimeException('SMTP unavailable'));
    $facility = Facility::create(['facility_name' => 'Mail Failure Gym', 'capacity' => 100, 'status' => 'available']);
    $data = validPublicReservationData($facility);
    $data['permit'] = UploadedFile::fake()->create('permit.pdf', 50, 'application/pdf');
    $this->post(route('reservation.store'), $data)->assertSessionHas('reservation_email_sent', false);
    $reservation = Reservation::sole();
    expect($reservation->status)->toBe('new');
    $this->get(route('reservation.success', $reservation->reference_number))->assertOk()
        ->assertSee('Your request is saved, but we could not send the confirmation email.');
    $this->get(route('reservation.track', ['reference' => $reservation->reference_number]))
        ->assertOk()->assertSee($reservation->reference_number);
});

test('verified event form submits the calendar date and restores it after validation errors', function () {
    $date = now()->addDays(4)->toDateString();
    $this->withSession([
        'public_reservation_details' => ['contact_email' => 'juan@example.com'],
        'public_email_verified' => ['email' => 'juan@example.com', 'verified' => true, 'expires_at' => now()->addMinutes(30)->timestamp],
        '_old_input' => ['reservation_date' => $date],
    ])->get(route('reservation.create', ['step' => 3]))->assertOk()
        ->assertSee('name="reservation_date" x-model="date" value="'.$date.'"', false);
});
