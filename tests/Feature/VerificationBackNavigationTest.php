<?php

use App\Mail\PublicRegistrationCode;
use App\Models\Facility;
use App\Models\Reservation;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Mail::fake();
    Storage::fake('local');
    $this->travelTo(now()->startOfSecond());
    Facility::create(['facility_name' => 'MCST Gymnasium', 'capacity' => 2000, 'status' => 'available']);
    $this->details = ['email' => 'verified@example.com', 'contact_person' => 'Requestor',
        'contact_number' => '09123456789', 'reservation_type' => 'external', 'purpose' => 'Practice'];
    $this->expires = now()->addMinutes(30)->timestamp;
    $this->withSession(['public_email_verified' => ['email' => 'verified@example.com', 'verified' => true, 'expires_at' => $this->expires],
        'public_reservation_details' => ['contact_email' => 'verified@example.com', 'contact_person' => 'Requestor', 'reservation_type' => 'external']]);
});

test('editing and returning to step one preserve email verification and its original expiry', function () {
    $this->postJson(route('reservation.edit_details'))->assertOk()->assertJsonPath('verified', true)
        ->assertJsonPath('expires_at', $this->expires);
    $this->get(route('reservation.create', ['step' => 1]))->assertOk()->assertSessionHas('public_email_verified');
    $this->get(route('reservation.create', ['step' => 3]))->assertOk();
    expect(session('public_email_verified.expires_at'))->toBe($this->expires);
    Mail::assertNothingSent();
});

test('non-email detail changes are saved without sending another code or extending verification', function () {
    $changed = [...$this->details, 'contact_person' => 'Updated Requestor', 'contact_number' => '09999999999', 'reservation_type' => 'internal'];
    $this->postJson(route('reservation.edit_details'), $changed)->assertOk()->assertJsonPath('verified', true)
        ->assertJsonPath('expires_at', $this->expires);
    expect(session('public_reservation_details.contact_person'))->toBe('Updated Requestor')
        ->and(session('public_reservation_details.reservation_type'))->toBe('internal');
    $this->postJson(route('reservation.send_code'), $changed)->assertOk()->assertJsonPath('verified', true);
    expect(session('public_email_verified.expires_at'))->toBe($this->expires);
    Mail::assertNothingSent();
});

test('email case changes preserve verification but a different email invalidates proof', function () {
    $this->postJson(route('reservation.edit_details'), [...$this->details, 'email' => 'VERIFIED@example.com'])
        ->assertOk()->assertJsonPath('verified', true);
    $this->postJson(route('reservation.edit_details'), [...$this->details, 'email' => 'different@example.com'])
        ->assertOk()->assertJsonPath('verified', false)->assertSessionMissing('public_email_verified');
    $this->get(route('reservation.create', ['step' => 3]))->assertRedirect(route('reservation.create'));
    Mail::assertNothingSent();
});

test('expired verification requires a new code and does not silently renew', function () {
    $this->travel(31)->minutes();
    $this->postJson(route('reservation.edit_details'), $this->details)->assertOk()->assertJsonPath('verified', false)
        ->assertJsonPath('challenge_active', false)->assertSessionMissing('public_email_verified');
    $this->postJson(route('reservation.send_code'), $this->details)->assertOk()->assertSessionHas('public_email_challenge');
    Mail::assertSentCount(1);
    Mail::assertSent(PublicRegistrationCode::class);
});

test('a pending unexpired code survives back navigation without another email', function () {
    $this->withSession(['public_email_verified' => null]);
    $this->postJson(route('reservation.send_code'), $this->details)->assertOk();
    $challenge = session('public_email_challenge');
    $this->postJson(route('reservation.edit_details'), [...$this->details, 'contact_person' => 'Updated name'])
        ->assertOk()->assertJsonPath('verified', false)->assertJsonPath('challenge_active', true);
    expect(session('public_email_challenge.hash'))->toBe($challenge['hash']);
    Mail::assertSentCount(1);
});

test('a reservation can be submitted after changing details without another code', function () {
    $this->postJson(route('reservation.edit_details'), [...$this->details, 'reservation_type' => 'internal'])->assertJsonPath('verified', true);
    $this->post(route('reservation.store'), ['reservation_type' => 'internal', 'event_name' => 'Updated event', 'purpose' => 'Updated purpose',
        'contact_person' => 'Updated Requestor', 'contact_email' => 'verified@example.com', 'contact_number' => '09999999999',
        'expected_attendees' => 100, 'reservation_date' => now()->addDays(5)->toDateString(), 'start_time' => '09:00', 'end_time' => '11:00',
        'agreement' => 1, 'agreement_accepted' => 1, 'permit' => \Illuminate\Http\UploadedFile::fake()->create('letter.pdf', 10, 'application/pdf'),
    ])->assertSessionHasNoErrors();
    expect(Reservation::sole()->contact_person)->toBe('Updated Requestor');
    Mail::assertNotSent(PublicRegistrationCode::class);
});
