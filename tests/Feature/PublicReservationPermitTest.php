<?php

use App\Models\Facility;
use App\Models\Reservation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

function validPublicReservationData(Facility $facility): array
{
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
        'reservation_date' => now()->addWeek()->format('Y-m-d'),
        'start_time' => '09:00',
        'end_time' => '11:00',
        'agreement' => '1',
    ];
}

test('a verification code is emailed and stored only after delivery succeeds', function () {
    Mail::shouldReceive('raw')->once();

    $this->postJson(route('reservation.verification.send'), [
        'contact_person' => 'Juan Dela Cruz',
        'contact_email' => 'juan@example.com',
        'contact_number' => '09123456789',
        'reservation_type' => 'community',
    ])->assertOk()
        ->assertJsonPath('message', 'A 6-digit verification code was sent to your email.')
        ->assertSessionHas('public_reservation_verification');
});

test('a public reservation stores its required permit', function () {
    Storage::fake('local');

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

    $response = $this->withSession([
        'public_reservation_verified_email' => 'juan@example.com',
    ])->post(route('reservation.store'), $data);

    $reservation = Reservation::firstOrFail();
    $document = $reservation->documents()->firstOrFail();

    $response->assertRedirect(
        route('reservation.success', $reservation->reference_number)
    );
    expect($document->document_type)->toBe('permit')
        ->and($document->original_filename)->toBe('gym-permit.pdf')
        ->and($document->uploaded_by)->toBeNull();
    Storage::disk('local')->assertExists($document->file_path);
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

    $this->withSession([
        'public_reservation_verified_email' => 'juan@example.com',
    ])->post(route('reservation.store'), $data)
        ->assertSessionHasErrors('permit');
});

test('a public reservation cannot bypass email verification', function () {
    Storage::fake('local');
    $facility = Facility::create([
        'facility_name' => 'Verification Gym',
        'capacity' => 100,
        'status' => 'available',
    ]);
    $data = validPublicReservationData($facility);
    $data['permit'] = UploadedFile::fake()->create('permit.pdf', 50, 'application/pdf');

    $this->post(route('reservation.store'), $data)
        ->assertSessionHasErrors('contact_email');

    $this->assertDatabaseCount('reservations', 0);
});
