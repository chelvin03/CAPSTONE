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
        'reservation_date' => now()->addDays(3)->format('Y-m-d'),
        'start_time' => '09:00',
        'end_time' => '11:00',
        'agreement' => '1',
    ];
}

test('a public reservation stores its required permit', function () {
    Storage::fake('local');
    Mail::shouldReceive('raw')->once();

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

test('public reservations can be submitted without email verification', function () {
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
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $this->assertDatabaseHas('reservations', ['contact_email' => 'unverified@example.com']);
});
