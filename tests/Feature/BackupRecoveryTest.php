<?php

use App\Models\BackupRecord;
use App\Models\Facility;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Support\Facades\Storage;

test('admin can access backup management and create a verified backup', function () {
    Storage::fake('local');
    config(['gym.backup.disk' => 'local', 'gym.backup.directory' => 'backups']);

    $admin = User::factory()->create(['role' => 'admin', 'status' => 'approved']);

    $this->actingAs($admin)
        ->get(route('admin.backups.index'))
        ->assertOk()
        ->assertSee('Backup &amp; Recovery', false)
        ->assertSee('Create Full Backup');

    $this->post(route('admin.backups.store'))
        ->assertRedirect()
        ->assertSessionHas('success', 'Encrypted backup created and verified successfully.');

    $backup = BackupRecord::firstOrFail();
    Storage::disk('local')->assertExists($backup->path);
    expect($backup->checksum)->toHaveLength(64)
        ->and($backup->verified_at)->not->toBeNull();
});

test('verified recovery restores database records and uploaded files', function () {
    Storage::fake('local');
    config(['gym.backup.disk' => 'local', 'gym.backup.directory' => 'backups']);

    $admin = User::factory()->create(['role' => 'admin', 'status' => 'approved']);
    $facility = Facility::create([
        'facility_name' => 'Recovery Gym',
        'capacity' => 150,
        'status' => 'available',
    ]);
    Storage::disk('local')->put('reservation-documents/permit.txt', 'original permit');

    $service = app(BackupService::class);
    $backup = $service->create($admin->id);

    $facility->update(['facility_name' => 'Changed Gym']);
    Storage::disk('local')->put('reservation-documents/permit.txt', 'changed permit');

    $manifest = $service->restore($backup);

    expect($manifest['format'])->toBe('mcst-gym-backup')
        ->and(Facility::findOrFail($facility->id)->facility_name)->toBe('Recovery Gym')
        ->and(Storage::disk('local')->get('reservation-documents/permit.txt'))->toBe('original permit')
        ->and($backup->fresh()->status)->toBe('restored');
});

test('non admins cannot access backup management', function () {
    $staff = User::factory()->create(['role' => 'staff', 'status' => 'approved']);

    $this->actingAs($staff)
        ->get(route('admin.backups.index'))
        ->assertForbidden();
});
