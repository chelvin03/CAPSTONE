<?php

use App\Models\Facility;
use App\Models\Reservation;
use App\Models\ScheduleBlock;
use Illuminate\Support\Facades\Schema;

test('public reserve opens the landing page before the existing request form', function () {
    $this->get('/reserve')->assertOk()->assertViewIs('public.home')
        ->assertSee(route('reservation.create'))->assertDontSee('name="contact_person"', false);
    $this->get(route('reservation.create'))->assertOk()->assertViewIs('public.reservations.create');
});

test('public schedule shows approved occupancy and blocks without private details', function () {
    $facility = Facility::create(['facility_name' => 'Test Gymnasium', 'capacity' => 100, 'status' => 'available']);
    $attributes = [
        'facility_id' => $facility->id, 'reservation_type' => 'community',
        'event_name' => 'Private event title', 'purpose' => 'Private purpose',
        'contact_person' => 'Private Requestor', 'contact_email' => 'private@example.com',
        'contact_number' => '09123456789', 'expected_attendees' => 20,
        'reservation_date' => now()->toDateString(), 'start_time' => '09:00', 'end_time' => '11:00',
    ];
    Reservation::create([...$attributes, 'reference_number' => 'PRIVATE-APPROVED', 'status' => 'approved']);
    Reservation::create([...$attributes, 'reference_number' => 'PRIVATE-PENDING', 'status' => 'pending', 'start_time' => '13:00', 'end_time' => '14:00']);
    ScheduleBlock::create(['starts_on' => now()->toDateString(), 'ends_on' => now()->toDateString(), 'title' => 'Private block title', 'created_by' => \App\Models\User::factory()->create(['role' => 'admin'])->id]);
    foreach (['/', '/schedule?month='.now()->format('Y-m')] as $url) {
        $this->get($url)->assertOk()->assertSee('Test Gymnasium')->assertSee('9:00 AM')
            ->assertSee('Occupied')->assertSee('Blocked')->assertSee('All day')
            ->assertDontSee('1:00 PM')->assertDontSee('Private Requestor')->assertDontSee('private@example.com')
            ->assertDontSee('Private event title')->assertDontSee('PRIVATE-APPROVED')->assertDontSee('Private block title');
    }
});

test('empty public schedule does not promise availability', function () {
    $this->get('/schedule')->assertOk()->assertSee('No occupied schedules to display')
        ->assertSee('This does not confirm');
    $this->get('/track')->assertOk();
    $this->get('/reserve')->assertOk();
    $this->get('/login')->assertOk();
});

test('homepage remains readable when schedule storage is unavailable', function () {
    Schema::drop('schedule_blocks');
    $this->get('/')->assertOk()->assertSee('Schedule temporarily unavailable')->assertSee('Reserve Now');
});

test('public schedule rejects invalid months', function () {
    $this->getJson('/schedule?month=invalid')->assertUnprocessable()->assertJsonValidationErrors('month');
});
