<?php

use App\Models\AcknowledgementReceipt;
use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;
use App\Services\AcknowledgementReceiptService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

beforeEach(function () {
    Mail::fake();
    $this->staff = User::factory()->create(['role' => 'staff']);
    $this->admin = User::factory()->create(['role' => 'admin']);
    $gym = Facility::create(['facility_name' => 'MCST Gymnasium', 'capacity' => 2000, 'status' => 'available']);
    $this->booking = Reservation::create(['reference_number' => 'WORKFLOW-AR', 'facility_id' => $gym->id,
        'reservation_type' => 'internal', 'event_name' => 'Sports Event', 'purpose' => 'Practice', 'contact_person' => 'Coordinator',
        'contact_number' => '09123456789', 'contact_email' => 'requestor@example.com', 'expected_attendees' => 50,
        'reservation_date' => now()->addDays(5)->toDateString(), 'start_time' => '09:00', 'end_time' => '11:00',
        'status' => 'approved', 'approved_by' => $this->admin->id, 'approved_at' => now()]);
});

test('staff can prepare review and email AR without gaining reservation edit permissions', function () {
    $this->actingAs($this->staff)->get(route('staff.reservations.receipt', $this->booking))->assertOk()->assertSee('Prepare AR');
    $this->get(route('staff.reservations.receipt.print', $this->booking))->assertStatus(409);
    $this->post(route('staff.reservations.receipt.prepare', $this->booking), ['notes' => 'Please arrive on time.'])->assertSessionHasNoErrors();
    $receipt = AcknowledgementReceipt::sole();
    Mail::assertNothingSent();
    $this->get(route('staff.reservations.receipt.print', $this->booking))->assertOk()->assertSee('Please arrive on time.');
    $this->post(route('staff.reservations.receipt.send', $this->booking), ['version' => $receipt->version])->assertSessionHasNoErrors();
    $mail = Mail::sent(\App\Mail\AcknowledgementReceiptReady::class)->sole();
    expect($mail->hasTo('requestor@example.com'))->toBeTrue()->and($receipt->fresh()->sent_at)->not->toBeNull();
    $this->get($mail->printUrl)->assertOk()->assertSee('Sports Event');
    foreach (['approve', 'reject', 'cancel', 'reschedule'] as $action) {
        $this->patch(route('admin.reservations.'.$action, $this->booking), [])->assertForbidden();
    }
    $this->get(route('staff.reports.export'))->assertForbidden();
    expect($this->booking->fresh()->status)->toBe('approved');
});

test('AR preparation stays idempotent and booking changes require fresh preparation', function () {
    $this->actingAs($this->staff)->post(route('staff.reservations.receipt.prepare', $this->booking), [])->assertSessionHasNoErrors();
    $receipt = AcknowledgementReceipt::sole();
    $this->post(route('staff.reservations.receipt.prepare', $this->booking), [])->assertSessionHasNoErrors();
    expect($receipt->fresh()->version)->toBe($receipt->version);
    $this->booking->update(['start_time' => '10:00']);
    $this->post(route('staff.reservations.receipt.send', $this->booking), ['version' => $receipt->version])->assertSessionHasErrors('receipt');
    $this->post(route('staff.reservations.receipt.prepare', $this->booking), [])->assertSessionHasNoErrors();
    expect($receipt->fresh()->version)->not->toBe($receipt->version);
    $this->booking->update(['status' => 'cancelled']);
    $this->post(route('staff.reservations.receipt.prepare', $this->booking), [])->assertForbidden();
});

test('SMTP failure leaves prepared AR unsent for staff retry', function () {
    $this->actingAs($this->staff)->post(route('staff.reservations.receipt.prepare', $this->booking), [])->assertSessionHasNoErrors();
    $receipt = AcknowledgementReceipt::sole();
    Mail::shouldReceive('mailer')->with('smtp')->andThrow(new RuntimeException('Unavailable'));
    $this->post(route('staff.reservations.receipt.send', $this->booking), ['version' => $receipt->version])->assertSessionHasErrors('receipt');
    expect($receipt->fresh()->sent_at)->toBeNull();
});

test('existing signed requestor receipt links and admin receipt viewing are preserved', function () {
    $receipt = AcknowledgementReceipt::create([
        'reservation_id' => $this->booking->id, 'receipt_number' => 'AR-WORKFLOW-AR',
        'prepared_by' => $this->staff->id, 'version' => (string) Str::uuid(),
        'snapshot' => ['booking' => app(AcknowledgementReceiptService::class)->snapshot($this->booking), 'prepared_name' => $this->staff->full_name],
        'prepared_at' => now(), 'sent_at' => now(), 'sent_to' => 'requestor@example.com',
    ]);
    $url = URL::temporarySignedRoute('public.reservations.receipt', now()->addDays(7), ['receipt' => $receipt->id, 'version' => $receipt->version], absolute: false);
    $this->get($url)->assertOk()->assertSee('Sports Event');
    $this->get(str_replace($receipt->version, (string) Str::uuid(), $url))->assertForbidden();
    $this->actingAs($this->admin)->get(route('admin.reservations.receipt', $this->booking))->assertOk();
    $this->booking->update(['status' => 'cancelled']);
    $this->get($url)->assertForbidden();
});
