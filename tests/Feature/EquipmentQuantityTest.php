<?php

use App\Models\Equipment;
use App\Models\Facility;
use App\Models\Reservation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Mail::fake();
    Storage::fake('local');
    Facility::create(['facility_name' => 'MCST Gymnasium', 'capacity' => 2000, 'status' => 'available']);
    $this->chairs = Equipment::create(['equipment_name' => 'Chairs', 'total_quantity' => 100, 'unit' => 'pieces', 'status' => 'available']);
    $this->payload = ['reservation_type' => 'internal', 'event_name' => 'Practice', 'purpose' => 'Sports',
        'contact_person' => 'Requestor', 'contact_email' => 'requestor@example.com', 'contact_number' => '09123456789',
        'expected_attendees' => 100, 'reservation_date' => now()->addDays(5)->toDateString(), 'start_time' => '09:00', 'end_time' => '11:00',
        'agreement' => 1, 'agreement_accepted' => 1, 'permit' => UploadedFile::fake()->create('letter.pdf', 10, 'application/pdf')];
    $this->withSession(['public_email_verified' => ['email' => 'requestor@example.com', 'verified' => true,
        'expires_at' => now()->addMinutes(30)->timestamp]]);
});

test('equipment rejects leading zeros and other noncanonical quantities with a readable error', function ($quantity) {
    $field = 'equipment.'.$this->chairs->id.'.quantity_requested';
    $this->post(route('reservation.store'), $this->payload + ['equipment' => [$this->chairs->id => ['quantity_requested' => $quantity]]])
        ->assertSessionHasErrors($field);
    $messages = session('errors')->getBag('default')->get($field);
    expect($messages[0])->toContain('Quantity for Chairs', 'without leading zeros')->not->toContain('equipment.');
    expect(Reservation::count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBeEmpty();
    Mail::assertNothingSent();
})->with(['010', '001', '00', '0', '-1', '1.5', '1e2', '+10', '10.0']);

test('ordinary positive quantities remain accepted and stored accurately', function ($quantity) {
    $this->post(route('reservation.store'), $this->payload + ['equipment' => [$this->chairs->id => ['quantity_requested' => $quantity]]])
        ->assertSessionHasNoErrors();
    expect(Reservation::sole()->equipment()->first()->pivot->quantity_requested)->toBe((int) $quantity);
})->with(['1', '10', '100', 10]);

test('custom equipment quantity uses the same leading zero rule', function () {
    $this->post(route('reservation.store'), $this->payload + ['requested_equipment' => 'Extra net', 'requested_equipment_quantity' => '001'])
        ->assertSessionHasErrors('requested_equipment_quantity');
    expect(Reservation::count())->toBe(0);
});

test('optional equipment can remain empty and quantity input preserves raw text for validation', function () {
    $this->get(route('reservation.create'))->assertOk()
        ->assertSee('type="text" inputmode="numeric" pattern="[1-9][0-9]*"', false)
        ->assertSee('Enter a positive whole number without leading zeros');
    $this->post(route('reservation.store'), $this->payload)->assertSessionHasNoErrors();
    expect(Reservation::sole()->equipment)->toBeEmpty();
});
