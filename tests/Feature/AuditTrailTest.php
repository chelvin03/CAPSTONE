<?php

use App\Models\AuditLog;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('timestamp diagnostics identify offsets without accepting or rewriting altered history', function () {
    AuditLog::recordEvent('system.timestamp');
    $log = AuditLog::query()->firstOrFail();
    $original = $log->created_at->format('Y-m-d H:i:s');
    $shifted = $log->created_at->copy()->addHours(15)->format('Y-m-d H:i:s');
    DB::table('audit_logs')->where('id', $log->id)->update(['created_at' => $shifted]);
    $log->refresh();

    expect($log->timestampMatchingHash())->toBe($original)
        ->and(AuditLog::verifyChain()['valid'])->toBeFalse()
        ->and($log->fresh()->created_at->format('Y-m-d H:i:s'))->toBe($shifted);

    DB::table('audit_logs')->where('id', $log->id)->update(['description' => 'tampered']);
    expect($log->fresh()->timestampMatchingHash())->toBeNull();
});

test('audit hashes use values normalized by database storage', function () {
    // Simulate a JSON database column changing object key order on insert.
    DB::unprepared("CREATE TRIGGER normalize_audit_json AFTER INSERT ON audit_logs
        BEGIN
            UPDATE audit_logs SET new_values = '{\"a\":1,\"b\":2}' WHERE id = NEW.id;
        END");

    AuditLog::recordEvent('system.normalized', newValues: ['b' => 2, 'a' => 1]);

    expect(AuditLog::query()->firstOrFail()->new_values)->toBe(['a' => 1, 'b' => 2])
        ->and(AuditLog::verifyChain()['valid'])->toBeTrue();
});

test('audit verification supports retained keys and still detects tampering', function () {
    $originalKey = config('app.key');
    AuditLog::recordEvent('system.before_rotation');

    config(['app.key' => 'base64:'.base64_encode(str_repeat('r', 32)), 'app.previous_keys' => []]);
    expect(AuditLog::verifyChain()['valid'])->toBeFalse();

    config(['app.previous_keys' => [$originalKey]]);
    AuditLog::recordEvent('system.after_rotation');
    expect(AuditLog::verifyChain()['valid'])->toBeTrue();

    $first = AuditLog::query()->orderBy('id')->firstOrFail();
    DB::table('audit_logs')->where('id', $first->id)->update(['description' => 'tampered']);
    expect(AuditLog::verifyChain())->toBe(['valid' => false, 'checked' => 1, 'failed_id' => $first->id]);
});

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
