<?php

use App\Models\Equipment;
use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;

test('admin dashboard statistics reflect database records', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $facility = Facility::create([
        'facility_name' => 'Dashboard Gym',
        'capacity' => 200,
        'status' => 'available',
    ]);

    Equipment::create([
        'equipment_name' => 'Available Balls',
        'total_quantity' => 12,
        'unit' => 'piece',
        'status' => 'available',
    ]);
    Equipment::create([
        'equipment_name' => 'Unavailable Nets',
        'total_quantity' => 50,
        'unit' => 'piece',
        'status' => 'unavailable',
    ]);

    foreach (['new', 'approved', 'approved', 'cancelled', 'completed'] as $index => $status) {
        Reservation::create([
            'reference_number' => 'DASH-'.$index,
            'user_id' => $admin->id,
            'facility_id' => $facility->id,
            'reservation_type' => 'school',
            'event_name' => 'Dashboard Event '.$index,
            'purpose' => 'Dashboard calculation test',
            'contact_person' => 'Test Requestor '.$index,
            'contact_number' => '09123456789',
            'expected_attendees' => 20,
            'reservation_date' => now()->startOfMonth()->addDays($index + 1)->toDateString(),
            'start_time' => '08:00',
            'end_time' => '09:00',
            'status' => $status,
        ]);
    }

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertViewHas('totalReservations', 5)
        ->assertViewHas('thisMonthReservations', 5)
        ->assertViewHas('pendingReservations', 1)
        ->assertViewHas('approvedReservations', 2)
        ->assertViewHas('approvalRate', 40)
        ->assertViewHas('cancelledReservations', 1)
        ->assertViewHas('thisMonthCancellations', 1)
        ->assertViewHas('facilityCount', 1)
        ->assertViewHas('availableEquipment', 12)
        ->assertViewHas('completedEvents', 1)
        ->assertSee('DASH-4')
        ->assertSee(route('admin.reservations.create'), false)
        ->assertSee(route('admin.reservations.index'), false);
});
