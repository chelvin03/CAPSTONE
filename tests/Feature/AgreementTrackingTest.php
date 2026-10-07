<?php
use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

function activeAgreementPayload(): array {
    test()->withSession(['public_email_verified' => ['email' => 'policy@example.com', 'verified' => true, 'expires_at' => now()->addMinutes(30)->timestamp]]);
    $facility = Facility::create(['facility_name' => 'Policy Gym', 'capacity' => 2000, 'status' => 'available']);
    return ['facility_id' => $facility->id, 'reservation_type' => 'internal', 'event_name' => 'Policy Test Event', 'purpose' => 'School activity',
        'contact_person' => 'Policy Requestor', 'contact_email' => 'policy@example.com', 'contact_number' => '09123456789',
        'expected_attendees' => 50, 'reservation_date' => now()->addDays(5)->toDateString(), 'start_time' => '09:00', 'end_time' => '11:00',
        'permit' => UploadedFile::fake()->create('permit.pdf', 20, 'application/pdf'), 'agreement' => '1', 'agreement_accepted' => '1'];
}

test('submission shows the reference immediately and keeps it available after leaving the tracking page', function () {
    Storage::fake('local'); Mail::fake(); $this->travelTo(now()->startOfSecond());
    $data=activeAgreementPayload(); $data['agreement_accepted_at']='2000-01-01 00:00:00';
    $response=$this->post(route('reservation.store'),$data)->assertSessionHasNoErrors();
    $reservation=Reservation::sole();
    $response->assertRedirect(route('reservation.track',['reference'=>$reservation->reference_number]))
        ->assertSessionHas('public_reservation_references',[$reservation->reference_number]);
    $this->followRedirects($response)->assertOk()->assertSee('Reservation Submitted Successfully')
        ->assertSee($reservation->reference_number)->assertSee('Copy Reference Number');
    $this->get(route('reservation.create',['step'=>1]))->assertOk();
    $this->get(route('reservation.track'))->assertOk()->assertSee($reservation->reference_number)->assertSee('My Reservation History');
    expect($reservation->agreement_accepted)->toBeTrue()->and($reservation->agreement_accepted_at->equalTo(now()))->toBeTrue();
    $this->actingAs(User::factory()->create(['role'=>'admin']))->get(route('admin.reservations.show',$reservation))
        ->assertOk()->assertSee('Gymnasium Use Agreement')->assertSee('Accepted')->assertSee('Policy Requestor');
});

test('missing or declined policy acceptance blocks saving uploading and success notification', function ($value) {
    Storage::fake('local'); Mail::fake(); $data=activeAgreementPayload(); unset($data['agreement_accepted']);
    if ($value!==null) $data['agreement_accepted']=$value;
    $this->post(route('reservation.store'),$data)->assertSessionHasErrors(['agreement_accepted'=>'Please read and accept the MCST Gymnasium Use Agreement before submitting your reservation request.']);
    $this->assertDatabaseCount('reservations',0); $this->assertDatabaseCount('reservation_documents',0);
    expect(Storage::disk('local')->allFiles())->toBeEmpty(); Mail::assertNothingSent();
})->with([null,'0','false']);

test('verified event form displays the full policy before the submit button', function () {
    $this->withSession(['public_email_verified'=>['email'=>'policy@example.com','verified'=>true,'expires_at'=>now()->addMinutes(30)->timestamp],
        'public_reservation_details'=>['contact_email'=>'policy@example.com','reservation_type'=>'external']])
        ->get(route('reservation.create',['step'=>3]))->assertOk()->assertSee('MCST Gymnasium Use Agreement')
        ->assertSee('No Food Inside the Gymnasium')->assertSee('Maintain Cleanliness')->assertSee('Responsibility for Damages')
        ->assertSee('Proper Use of the Gymnasium')->assertSee('name="agreement_accepted"',false);
});
test('invalid attendance cannot create a reservation or upload a permit', function ($attendees) {
    Storage::fake('local'); Mail::fake();
    $data = activeAgreementPayload(); $data['expected_attendees'] = $attendees;
    $this->from(route('reservation.create', ['step' => 3]))->post(route('reservation.store'), $data)
        ->assertRedirect(route('reservation.create', ['step' => 3]))
        ->assertSessionHasErrors('expected_attendees')->assertSessionHasInput('event_name', 'Policy Test Event');
    $this->assertDatabaseCount('reservations', 0);
    $this->assertDatabaseCount('reservation_documents', 0);
    expect(Storage::disk('local')->allFiles())->toBeEmpty();
    Mail::assertNothingSent();
})->with([2001, 2500, 3000, 0, -1, '1.5']);

test('exact gym capacity can be submitted', function () {
    Storage::fake('local'); Mail::fake();
    $data = activeAgreementPayload(); $data['expected_attendees'] = 2000;
    $this->post(route('reservation.store'), $data)->assertSessionHasNoErrors();
    expect(Reservation::sole()->expected_attendees)->toBe(2000);
});

test('admins cannot create or edit reservations above gym capacity', function () {
    Storage::fake('local'); Mail::fake();
    $data = activeAgreementPayload();
    $this->post(route('reservation.store'), $data)->assertSessionHasNoErrors();
    $reservation = Reservation::sole();
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $data['expected_attendees'] = 2001;
    $this->post(route('admin.reservations.store'), $data)->assertSessionHasErrors('expected_attendees');
    $this->put(route('admin.reservations.update', $reservation), $data)->assertSessionHasErrors('expected_attendees');
    expect($reservation->fresh()->expected_attendees)->toBe(50);
    $this->assertDatabaseCount('reservations', 1);
});