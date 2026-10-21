<?php

use App\Models\Facility;
use App\Models\Reservation;
use App\Models\ReservationStatusHistory;
use App\Models\RequestorRestriction;
use App\Models\User;
use App\Services\RequestorCancellationReviewService;

beforeEach(function () {
    $this->travelTo(now()->startOfSecond());
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->gym = Facility::create(['facility_name' => 'MCST Gymnasium', 'capacity' => 2000, 'status' => 'available']);
});

function reviewBooking(Facility $gym, string $email, string $source = 'requestor'): Reservation
{
    $booking = Reservation::create(['reference_number' => 'REVIEW-'.strtoupper(uniqid()), 'facility_id' => $gym->id,
        'reservation_type' => 'internal', 'event_name' => 'Sports', 'purpose' => 'Practice', 'contact_person' => 'Requestor',
        'contact_email' => $email, 'contact_number' => '09123456789', 'expected_attendees' => 100,
        'reservation_date' => now()->addDays(5)->toDateString(), 'start_time' => '09:00', 'end_time' => '11:00',
        'status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => 'Plans changed']);
    if ($source !== 'unknown') {
        ReservationStatusHistory::create(['reservation_id' => $booking->id, 'new_status' => 'cancelled',
            'remarks' => $source === 'requestor' ? 'Cancelled by requestor: Plans changed' : 'Administrator schedule block']);
    }
    return $booking;
}

test('review aggregates normalized emails and excludes admin and legacy cancellations', function () {
    foreach (['User@example.com', 'user@example.com', 'USER@example.com'] as $email) reviewBooking($this->gym, $email);
    reviewBooking($this->gym, 'user@example.com', 'admin')->update(['cancelled_by' => $this->admin->id]);
    reviewBooking($this->gym, 'user@example.com', 'unknown');
    reviewBooking($this->gym, 'old@example.com')->forceFill(['created_at' => now()->subDays(91)])->save();
    $report = app(RequestorCancellationReviewService::class)->report();
    expect($report['reviewRows'])->toHaveCount(1);
    $row = $report['reviewRows']->first();
    expect($row['email'])->toBe('user@example.com')->and($row['total'])->toBe(5)
        ->and($row['cancelled'])->toHaveCount(3)->and($row['rate'])->toBe(60.0)->and($row['flagged'])->toBeTrue();
    expect(RequestorRestriction::count())->toBe(0);
    $this->actingAs($this->admin)->get(route('admin.requestor-restrictions.index'))->assertOk()->assertSee('Flagged for review (3+)');
});

test('admin restriction and release require reasons and leave audit history', function () {
    $this->actingAs($this->admin)->post(route('admin.requestor-restrictions.store'), [
        'email' => 'USER@example.com', 'reason' => 'Repeated cancellations reviewed.', 'expires_at' => now()->addDays(30)->toDateString(),
    ])->assertSessionHasNoErrors();
    $restriction = RequestorRestriction::sole();
    expect(RequestorRestriction::activeFor('user@EXAMPLE.com')?->id)->toBe($restriction->id);
    $this->delete(route('admin.requestor-restrictions.destroy', $restriction), [])->assertSessionHasErrors('revocation_reason');
    $this->delete(route('admin.requestor-restrictions.destroy', $restriction), ['revocation_reason' => 'Appeal accepted.'])->assertSessionHasNoErrors();
    expect(RequestorRestriction::activeFor('user@example.com'))->toBeNull();
    $this->assertDatabaseHas('audit_logs', ['action' => 'requestor.restricted', 'auditable_id' => $restriction->id]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'requestor.restriction_released', 'auditable_id' => $restriction->id]);
});

test('restriction requires future bounded expiry and reason', function ($overrides, $field) {
    $this->actingAs($this->admin)->post(route('admin.requestor-restrictions.store'), array_merge([
        'email' => 'user@example.com', 'reason' => 'Reviewed', 'expires_at' => now()->addDays(10)->toDateString(),
    ], $overrides))->assertSessionHasErrors($field);
    expect(RequestorRestriction::count())->toBe(0);
})->with([
    [['reason' => '   '], 'reason'], [['expires_at' => '2000-01-01'], 'expires_at'],
    [['expires_at' => '2099-01-01'], 'expires_at'],
]);

test('staff and guests cannot manage restrictions', function () {
    $this->get(route('admin.requestor-restrictions.index'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create(['role' => 'staff']))
        ->get(route('admin.requestor-restrictions.index'))->assertForbidden();
    $this->post(route('admin.requestor-restrictions.store'), [])->assertForbidden();
});

test('restriction expires automatically and is isolated to the normalized email', function () {
    RequestorRestriction::create(['email' => 'user@example.com', 'reason' => 'Reviewed', 'expires_at' => now()->addHour()]);
    expect(RequestorRestriction::activeFor(' User@Example.com '))->not->toBeNull()
        ->and(RequestorRestriction::activeFor('other@example.com'))->toBeNull();
    $this->travel(1)->hours();
    expect(RequestorRestriction::activeFor('user@example.com'))->toBeNull();
});

test('restricted verified email cannot submit a reservation but can still track and cancel', function () {
    \Illuminate\Support\Facades\Mail::fake();
    \Illuminate\Support\Facades\Storage::fake('local');
    $existing = reviewBooking($this->gym, 'user@example.com');
    $existing->update(['status' => 'approved']);
    RequestorRestriction::create(['email' => 'user@example.com', 'reason' => 'Repeated cancellations reviewed.', 'expires_at' => now()->addDays(10)]);
    $proof = ['public_email_verified' => ['email' => 'user@example.com', 'verified' => true, 'expires_at' => now()->addMinutes(30)->timestamp]];
    $this->withSession($proof)->post(route('reservation.store'), [
        'reservation_type' => 'internal', 'event_name' => 'Practice', 'purpose' => 'Sports', 'contact_person' => 'Requestor',
        'contact_email' => 'user@example.com', 'contact_number' => '09123456789', 'expected_attendees' => 100,
        'reservation_date' => now()->addDays(6)->toDateString(), 'start_time' => '09:00', 'end_time' => '11:00',
        'agreement' => 1, 'agreement_accepted' => 1,
        'permit' => \Illuminate\Http\UploadedFile::fake()->create('letter.pdf', 20, 'application/pdf'),
    ])->assertSessionHasErrors('contact_email');
    expect(Reservation::count())->toBe(1);
    $this->getJson(route('reservation.track', ['reference' => $existing->reference_number]))->assertOk();
    $this->post(route('reservation.cancel', $existing), ['cancellation_reason' => 'Event postponed.'])->assertSessionHasNoErrors();
    expect($existing->refresh()->status)->toBe('cancelled');
});
