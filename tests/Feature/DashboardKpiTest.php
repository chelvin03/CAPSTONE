<?php

use App\Models\Facility;
use App\Models\Reservation;
use App\Models\SystemSetting;
use App\Models\User;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 18)->startOfDay());
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->gym = Facility::create(['facility_name' => 'MCST Gymnasium', 'capacity' => 2000, 'status' => 'available']);
    $this->make = function (string $date = '2026-10-20', array $extra = []) {
        return Reservation::create(array_merge(['reference_number' => 'KPI-'.strtoupper(uniqid()), 'facility_id' => $this->gym->id,
            'user_id' => $this->admin->id, 'reservation_type' => 'internal', 'event_name' => 'School Event', 'purpose' => 'Practice',
            'contact_person' => 'Private Requestor', 'contact_number' => '09123456789', 'expected_attendees' => 100,
            'reservation_date' => $date, 'start_time' => '09:00', 'end_time' => '11:00', 'status' => 'approved'], $extra));
    };
    $this->scope = ['date_from' => '2026-10-20', 'date_to' => '2026-10-20'];
    $this->actingAs($this->admin);
});

test('KPIs separate required completeness from optional event type and preserve charts', function () {
    $booking = ($this->make)();
    $response = $this->get(route('admin.dashboard', $this->scope))->assertOk()
        ->assertSee('Performance against configured targets')->assertSee('Target not configured')
        ->assertSee('Monthly Reservation Trend')->assertDontSee('Required-field Completeness Overview')
        ->assertSee('6. Operational Performance')->assertSee('Includes future scheduled dates');
    $context = collect($response->viewData('kpiContextCards'))->keyBy('title');
    expect($context['Required-field Completeness']['value'])->toBe('100%')
        ->and($context['Event-type Coverage']['value'])->toBe('0%')
        ->and($context['Event-type Coverage']['evidence']->pluck('id')->all())->toBe([$booking->id]);
    foreach ($response->viewData('kpiCards') as $card) expect($card['target'])->toBeNull();
    $response->assertSee(route('admin.reservations.show', $booking), false)->assertDontSee('Private Requestor');
});

test('KPI comparisons preserve category filter and use percentage points', function () {
    ($this->make)('2026-10-20', ['event_type' => 'Sports', 'status' => 'cancelled']);
    ($this->make)('2026-10-19', ['event_type' => 'Sports']);
    ($this->make)('2026-10-19', ['event_type' => 'Other', 'status' => 'cancelled']);
    $response = $this->get(route('admin.dashboard', $this->scope + ['category' => 'Sports']))->assertOk();
    $cards = collect($response->viewData('kpiCards'))->keyBy('key');
    expect($cards['cancellations']['comparison'])->toContain('+100 percentage points', 'previous 0%')
        ->and($cards['overdue']['comparison'])->toContain('Historical backlog snapshots are unavailable');
});

test('empty and missing baseline data are not presented as measured improvements', function () {
    $response = $this->get(route('admin.dashboard', $this->scope))->assertOk();
    $cards = collect($response->viewData('kpiCards'))->keyBy('key');
    expect($cards['cancellations']['value'])->toBeNull()->and($cards['review_hours']['value'])->toBeNull()
        ->and($cards['cancellations']['comparison'])->toContain('No comparison data');
    $this->get(route('admin.dashboard', ['all_time' => 1]))->assertOk()->assertSee('Previous-period comparison is unavailable');
});

test('admin configured zero targets persist and evaluations use correct directions', function () {
    ($this->make)('2026-10-20', ['status' => 'cancelled']);
    $this->put(route('admin.dashboard.kpi-targets'), ['targets_reviewed' => 1,
        'kpi_utilization' => 10, 'kpi_review_hours' => 24, 'kpi_overdue' => 0, 'kpi_cancellations' => 0])->assertSessionHasNoErrors();
    expect(SystemSetting::getValue('kpi_cancellations'))->toBe('0');
    $cards = collect($this->get(route('admin.dashboard', $this->scope))->viewData('kpiCards'))->keyBy('key');
    expect($cards['cancellations']['met'])->toBeFalse()->and($cards['overdue']['met'])->toBeTrue()
        ->and($cards['utilization']['met'])->toBeFalse()->and($cards['review_hours']['met'])->toBeNull();
    $this->assertDatabaseHas('audit_logs', ['action' => 'analytics.kpi_targets_updated', 'user_id' => $this->admin->id]);
    $this->put(route('admin.dashboard.kpi-targets'), ['targets_reviewed' => 1, 'kpi_cancellations' => ''])->assertSessionHasNoErrors();
    $cards = collect($this->get(route('admin.dashboard', $this->scope))->viewData('kpiCards'))->keyBy('key');
    expect($cards['cancellations']['target'])->toBeNull();
});

test('invalid targets require review and cannot overwrite saved configuration', function () {
    $this->put(route('admin.dashboard.kpi-targets'), ['kpi_cancellations' => 101, 'kpi_overdue' => 1.5])
        ->assertSessionHasErrors(['targets_reviewed', 'kpi_cancellations', 'kpi_overdue']);
    expect(SystemSetting::where('key', 'like', 'kpi_%')->count())->toBe(0);
    $this->actingAs(User::factory()->create(['role' => 'staff']))->put(route('admin.dashboard.kpi-targets'), [])
        ->assertForbidden();
});

test('CSV exports KPI definitions targets comparison and scope', function () {
    ($this->make)();
    $csv = $this->get(route('admin.dashboard', $this->scope + ['export' => 'csv']))->assertOk()->streamedContent();
    expect($csv)->toContain('KPI reporting scope', 'Average Decision Time', 'Not configured', 'Required-field Completeness', 'Event-type Coverage');
});

test('decision time uses valid timestamps and comparisons report hours rather than percentages', function () {
    foreach ([24, 48] as $hours) {
        ($this->make)('2026-10-20', ['approved_at' => now()])->forceFill(['created_at' => now()->subHours($hours)])->save();
    }
    ($this->make)('2026-10-19', ['approved_at' => now()])->forceFill(['created_at' => now()->subHours(12)])->save();
    $cards = collect($this->get(route('admin.dashboard', $this->scope))->assertOk()->viewData('kpiCards'))->keyBy('key');
    expect($cards['review_hours']['value'])->toBe(36.0)
        ->and($cards['review_hours']['comparison'])->toBe('+24 hours vs previous 12 hours.')
        ->and($cards['review_hours']['evidence'])->toHaveCount(2);
});

test('saving targets preserves the selected dashboard filters', function () {
    $response = $this->put(route('admin.dashboard.kpi-targets'), $this->scope + ['targets_reviewed' => 1, 'kpi_overdue' => 0]);
    $response->assertRedirect(route('admin.dashboard', $this->scope));
});
