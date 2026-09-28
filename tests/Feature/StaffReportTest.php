<?php

use App\Models\AnalyticsReport;
use App\Models\User;

test('staff report pages generation and export are forbidden', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $this->actingAs($staff)->get(route('staff.reports.index'))->assertForbidden();
    $this->get(route('staff.reports.export'))->assertForbidden();
    $this->post(route('staff.reports.store'), ['report_type' => 'monthly', 'date_from' => '2026-09-01', 'date_to' => '2026-09-30'])->assertForbidden();
    expect(AnalyticsReport::count())->toBe(0);
});

test('admin retains admin reporting and cannot enter retired staff reports', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->get(route('admin.reports.index'))->assertOk();
    $this->get(route('staff.reports.index'))->assertForbidden();
    $this->get(route('staff.reports.export'))->assertForbidden();
});
