<?php

use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->staff = User::factory()->create(['role' => 'staff']);
    $gym = Facility::create(['facility_name' => 'MCST Gymnasium', 'capacity' => 2000, 'status' => 'available']);
    $this->booking = Reservation::create(['reference_number' => 'AR-TEST', 'facility_id' => $gym->id,
        'reservation_type' => 'internal', 'event_name' => 'Sports Day', 'purpose' => 'Practice', 'contact_person' => 'Coordinator',
        'contact_number' => '09123456789', 'expected_attendees' => 50, 'reservation_date' => now()->addDays(4)->toDateString(),
        'start_time' => '09:00', 'end_time' => '11:00', 'status' => 'new']);
});

test('admin approval lets staff prepare and print acknowledgement receipts', function () {
    $this->actingAs($this->admin)->get(route('admin.reservations.show', $this->booking))->assertOk()->assertSee('Approve / For AR');
    $this->patch(route('admin.reservations.approve', $this->booking))->assertSessionHasNoErrors();
    $this->actingAs($this->staff)->post(route('staff.reservations.receipt.prepare', $this->booking), [])->assertSessionHasNoErrors();
    $url = route('staff.reservations.receipt.print', $this->booking);
    $this->actingAs($this->staff)->get(route('staff.reservations.show', $this->booking))->assertOk()->assertSee('Prepare / Send AR');
    $this->get($url)->assertOk()->assertSee('ACKNOWLEDGEMENT RECEIPT')->assertSee('AR-AR-TEST')
        ->assertSee('Sports Day')->assertSee('Print / Save as PDF')->assertHeader('Cache-Control', 'no-store, private');
    $this->get($url)->assertOk()->assertSee('AR-AR-TEST');
    expect($this->booking->fresh()->status)->toBe('approved');
});

test('unapproved cancelled and suspended staff cannot print receipts', function () {
    $this->get(route('staff.reservations.receipt', $this->booking))->assertRedirect(route('login'));
    foreach (['new', 'pencil', 'cancelled', 'rejected'] as $status) {
        $this->booking->update(['status' => $status]);
        $this->actingAs($this->staff)->get(route('staff.reservations.receipt', $this->booking))->assertForbidden();
    }
    $this->booking->update(['status' => 'approved']);
    $this->staff->update(['status' => 'pending']);
    $this->get(route('staff.reservations.receipt', $this->booking))->assertForbidden();
});

test('admin letter preview serves existing private PDFs and is scoped to the reservation', function () {
    Storage::disk('local')->put('reservation-documents/test.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");
    $document = $this->booking->documents()->create(['document_type' => 'permit', 'original_filename' => 'Letter.pdf',
        'stored_filename' => 'test.pdf', 'file_path' => 'reservation-documents/test.pdf', 'mime_type' => 'application/pdf', 'file_size' => 50]);
    $url = route('admin.reservations.documents.preview', [$this->booking, $document]);
    $this->get($url)->assertRedirect(route('login'));
    $this->actingAs($this->staff)->get($url)->assertForbidden();
    $this->actingAs($this->admin)->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->get(route('admin.reservations.show', $this->booking))->assertOk()->assertSee('View Letter')->assertSee('Letter.pdf');
    $other = $this->booking->replicate(); $other->reference_number = 'OTHER-AR'; $other->save();
    $this->get(route('admin.reservations.documents.preview', [$other, $document]))->assertNotFound();
    Storage::disk('local')->delete('reservation-documents/test.pdf');
    $this->get($url)->assertNotFound();
});

test('unsupported uploaded content cannot be rendered as a letter', function () {
    Storage::disk('local')->put('reservation-documents/test.html', '<script>alert(1)</script>');
    $document = $this->booking->documents()->create(['document_type' => 'permit', 'original_filename' => 'Pretend.pdf',
        'stored_filename' => 'test.html', 'file_path' => 'reservation-documents/test.html', 'mime_type' => 'application/pdf', 'file_size' => 20]);
    $this->actingAs($this->admin)->get(route('admin.reservations.documents.preview', [$this->booking, $document]))->assertStatus(415);
});
