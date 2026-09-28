<?php

use App\Models\AuditLog;
use App\Models\Facility;
use App\Models\User;

test('critical model changes are recorded automatically with actor and integrity data', function () {
    $admin = User::factory()->create(['role' => 'admin', 'status' => 'approved']);
    $this->actingAs($admin);

    $facility = Facility::create(['facility_name' => 'Audit Gym', 'capacity' => 100, 'status' => 'available']);
    $facility->update(['capacity' => 125]);

    $created = AuditLog::query()->where('action', 'facility.created')->latest('id')->firstOrFail();
    $updated = AuditLog::query()->where('action', 'facility.updated')->latest('id')->firstOrFail();

    expect($created->actor_email)->toBe($admin->email)
        ->and($created->auditable_id)->toBe($facility->id)
        ->and($created->entry_hash)->toHaveLength(64)
        ->and($updated->old_values['capacity'])->toBe(100)
        ->and($updated->new_values['capacity'])->toBe(125)
        ->and(AuditLog::verifyChain()['valid'])->toBeTrue();
});

test('audit monitoring supports filters and csv reporting', function () {
    $admin = User::factory()->create(['role' => 'admin', 'status' => 'approved']);
    $this->actingAs($admin);
    Facility::create(['facility_name' => 'Searchable Audit Gym', 'capacity' => 80, 'status' => 'available']);

    $this->get(route('admin.audit.index', ['module' => 'facility', 'search' => 'created']))
        ->assertOk()
        ->assertSee('Audit integrity verified')
        ->assertSee('Searchable Audit Gym');

    $this->get(route('admin.audit.export', ['module' => 'facility']))
        ->assertOk()
        ->assertDownload();
});

test('audit records cannot be changed through the model', function () {
    $admin = User::factory()->create(['role' => 'admin', 'status' => 'approved']);
    $this->actingAs($admin);
    $facility = Facility::create(['facility_name' => 'Immutable Audit Gym', 'capacity' => 80, 'status' => 'available']);
    $log = AuditLog::query()->where('auditable_id', $facility->id)->latest('id')->firstOrFail();

    expect(fn () => $log->update(['description' => 'tampered']))
        ->toThrow(LogicException::class, 'Audit records are immutable.');
});
