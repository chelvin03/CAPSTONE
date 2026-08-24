<?php

use App\Models\Equipment;
use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;

beforeEach(function (): void {
    $this->admin = User::factory()->create([
        'role' => 'admin',
        'status' => 'approved',
    ]);

    $this->actingAs($this->admin);
});

test('facility maintenance supports validation and CRUD without affecting other records', function () {
    $other = Facility::create([
        'facility_name' => 'Existing Gym',
        'location' => 'Main Campus',
        'capacity' => 500,
        'status' => 'available',
    ]);

    $this->post(route('admin.facilities.store'), [
        'facility_name' => '',
        'capacity' => 0,
        'status' => 'invalid',
    ])->assertSessionHasErrors(['facility_name', 'capacity', 'status']);

    $createResponse = $this->post(route('admin.facilities.store'), [
        'facility_name' => 'QA Training Court',
        'description' => 'File maintenance test',
        'location' => 'North Wing',
        'capacity' => 120,
        'status' => 'available',
    ]);

    $createResponse
        ->assertRedirect(route('admin.facilities.index'))
        ->assertSessionHas('success', 'Facility added successfully.');

    $facility = Facility::where('facility_name', 'QA Training Court')->firstOrFail();

    $this->post(route('admin.facilities.store'), [
        'facility_name' => 'QA Training Court',
        'capacity' => 100,
        'status' => 'available',
    ])->assertSessionHasErrors('facility_name');

    $this->get(route('admin.facilities.index', ['search' => 'Training']))
        ->assertOk()
        ->assertSee('QA Training Court')
        ->assertDontSee('Existing Gym');

    $this->put(route('admin.facilities.update', $facility), [
        'facility_name' => 'QA Updated Court',
        'description' => 'Updated test',
        'location' => 'South Wing',
        'capacity' => 130,
        'status' => 'maintenance',
    ])->assertRedirect(route('admin.facilities.index'))
        ->assertSessionHas('success', 'Facility updated successfully.');

    $this->assertDatabaseHas('facilities', [
        'id' => $facility->id,
        'facility_name' => 'QA Updated Court',
        'capacity' => 130,
        'status' => 'maintenance',
    ]);
    $this->assertDatabaseHas('facilities', [
        'id' => $other->id,
        'facility_name' => 'Existing Gym',
    ]);

    $this->delete(route('admin.facilities.destroy', $facility))
        ->assertRedirect(route('admin.facilities.index'))
        ->assertSessionHas('success', 'Facility deleted successfully.');

    $this->assertDatabaseMissing('facilities', ['id' => $facility->id]);
    $this->assertDatabaseHas('facilities', ['id' => $other->id]);
});

test('a facility used by a reservation is retained with a helpful warning', function () {
    $facility = Facility::create([
        'facility_name' => 'Reserved Gym',
        'capacity' => 200,
        'status' => 'available',
    ]);

    Reservation::create([
        'reference_number' => 'QA-FACILITY-DELETE',
        'user_id' => $this->admin->id,
        'facility_id' => $facility->id,
        'reservation_type' => 'internal',
        'event_name' => 'QA Event',
        'purpose' => 'Deletion protection test',
        'contact_person' => 'QA Tester',
        'contact_number' => '09123456789',
        'expected_attendees' => 20,
        'reservation_date' => now()->addDay()->toDateString(),
        'start_time' => '08:00',
        'end_time' => '09:00',
        'status' => 'new',
    ]);

    $this->delete(route('admin.facilities.destroy', $facility))
        ->assertRedirect(route('admin.facilities.index'))
        ->assertSessionHas(
            'error',
            'This facility cannot be deleted because it is used by one or more reservations. Set its status to unavailable instead.'
        );

    $this->assertDatabaseHas('facilities', ['id' => $facility->id]);
});

test('equipment maintenance supports validation and CRUD without affecting other records', function () {
    $other = Equipment::create([
        'equipment_name' => 'Existing Basketball',
        'total_quantity' => 10,
        'unit' => 'piece',
        'status' => 'available',
    ]);

    $this->post(route('admin.equipment.store'), [
        'equipment_name' => '',
        'total_quantity' => -1,
        'unit' => '',
        'status' => 'invalid',
    ])->assertSessionHasErrors([
        'equipment_name',
        'total_quantity',
        'unit',
        'status',
    ]);

    $this->post(route('admin.equipment.store'), [
        'equipment_name' => 'QA Training Ball',
        'description' => 'File maintenance test',
        'total_quantity' => 25,
        'unit' => 'piece',
        'status' => 'available',
    ])->assertRedirect(route('admin.equipment.index'))
        ->assertSessionHas('success', 'Equipment added successfully.');

    $equipment = Equipment::where('equipment_name', 'QA Training Ball')->firstOrFail();

    $this->post(route('admin.equipment.store'), [
        'equipment_name' => 'QA Training Ball',
        'total_quantity' => 1,
        'unit' => 'piece',
        'status' => 'available',
    ])->assertSessionHasErrors('equipment_name');

    $this->get(route('admin.equipment.index', ['search' => 'Training']))
        ->assertOk()
        ->assertSee('QA Training Ball')
        ->assertDontSee('Existing Basketball');

    $this->put(route('admin.equipment.update', $equipment), [
        'equipment_name' => 'QA Updated Ball Set',
        'description' => 'Updated test',
        'total_quantity' => 30,
        'unit' => 'set',
        'status' => 'maintenance',
    ])->assertRedirect(route('admin.equipment.index'))
        ->assertSessionHas('success', 'Equipment updated successfully.');

    $this->assertDatabaseHas('equipment', [
        'id' => $equipment->id,
        'equipment_name' => 'QA Updated Ball Set',
        'total_quantity' => 30,
        'status' => 'maintenance',
    ]);
    $this->assertDatabaseHas('equipment', [
        'id' => $other->id,
        'equipment_name' => 'Existing Basketball',
    ]);

    $this->delete(route('admin.equipment.destroy', $equipment))
        ->assertRedirect(route('admin.equipment.index'))
        ->assertSessionHas('success', 'Equipment deleted successfully.');

    $this->assertDatabaseMissing('equipment', ['id' => $equipment->id]);
    $this->assertDatabaseHas('equipment', ['id' => $other->id]);
});

test('equipment used by a reservation is retained with a helpful warning', function () {
    $facility = Facility::create([
        'facility_name' => 'Equipment Test Gym',
        'capacity' => 200,
        'status' => 'available',
    ]);
    $equipment = Equipment::create([
        'equipment_name' => 'Reserved Ball',
        'total_quantity' => 10,
        'unit' => 'piece',
        'status' => 'available',
    ]);
    $reservation = Reservation::create([
        'reference_number' => 'QA-EQUIPMENT-DELETE',
        'user_id' => $this->admin->id,
        'facility_id' => $facility->id,
        'reservation_type' => 'internal',
        'event_name' => 'QA Equipment Event',
        'purpose' => 'Deletion protection test',
        'contact_person' => 'QA Tester',
        'contact_number' => '09123456789',
        'expected_attendees' => 20,
        'reservation_date' => now()->addDay()->toDateString(),
        'start_time' => '10:00',
        'end_time' => '11:00',
        'status' => 'new',
    ]);
    $reservation->equipment()->attach($equipment->id, [
        'quantity_requested' => 1,
    ]);

    $this->delete(route('admin.equipment.destroy', $equipment))
        ->assertRedirect(route('admin.equipment.index'))
        ->assertSessionHas(
            'error',
            'This equipment cannot be deleted because it is used by one or more reservations. Set its status to unavailable instead.'
        );

    $this->assertDatabaseHas('equipment', ['id' => $equipment->id]);
});

test('staff can only access staff maintenance lists', function () {
    $staff = User::factory()->create(['role' => 'staff']);

    $staffPages = [
        route('staff.dashboard'),
        route('staff.reservations.index'),
        route('staff.facilities.index'),
        route('staff.equipment.index'),
    ];

    $this->actingAs($staff);

    foreach ($staffPages as $staffPage) {
        $this->get($staffPage)
            ->assertOk()
            ->assertSee(route('staff.dashboard'), false)
            ->assertSee(route('staff.reservations.index'), false)
            ->assertSee(route('staff.facilities.index'), false)
            ->assertSee(route('staff.equipment.index'), false)
            ->assertDontSee(route('admin.dashboard'), false);
    }

    $this->get(route('admin.facilities.index'))
        ->assertForbidden();
});
