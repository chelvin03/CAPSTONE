<?php

use App\Mail\{ReservationFollowUp, ReservationScheduleChanged};
use App\Models\{Equipment, EquipmentReturn, Facility, RequestorRestriction, Reservation, ReservationReminder, ScheduleProposal, User};
use App\Services\{EquipmentRequestService, ReservationFollowUpService};
use Illuminate\Support\Facades\{Mail, URL};

beforeEach(function () {
    Mail::fake();
    $this->travelTo(now()->setDate(2026,10,18)->setTime(12,0));
    $this->admin = User::factory()->create(['role'=>'admin','status'=>'approved']);
    $this->staff = User::factory()->create(['role'=>'staff','status'=>'approved']);
    $this->gym = Facility::create(['facility_name'=>'MCST Gymnasium','capacity'=>2000,'status'=>'available']);
    $this->make = function (array $values = []) {
        return Reservation::create(array_replace(['reference_number'=>'OPS-'.strtoupper(uniqid()),'facility_id'=>$this->gym->id,
            'reservation_type'=>'internal','event_name'=>'School Event','purpose'=>'Sports','contact_person'=>'Requestor',
            'contact_email'=>'requestor@example.com','contact_number'=>'09123456789','expected_attendees'=>100,
            'reservation_date'=>'2026-10-19','start_time'=>'09:00','end_time'=>'11:00','status'=>'approved'], $values));
    };
    $this->equipment = Equipment::create(['equipment_name'=>'Chairs','total_quantity'=>10,'unit'=>'pieces','status'=>'available']);
    $this->past = ($this->make)(['reservation_date'=>'2026-10-17']);
    $this->past->equipment()->attach($this->equipment, ['quantity_requested'=>5,'quantity_approved'=>5,'status'=>'approved']);
    $this->returnData = ['issued'=>5,'returned_good'=>3,'damaged'=>1,'missing'=>1,'restored'=>0,'revision'=>0,'remarks'=>'Checked against signed issue sheet.'];
    $this->actingAs($this->admin);
    $this->returnUrl = route('admin.reservations.equipment.return', [$this->past,$this->equipment]);
    $this->offer = function (Reservation $booking, array $overrides = []) {
        $data = array_replace(['reservation_date'=>$booking->reservation_date->toDateString(),'start_time'=>substr($booking->start_time,0,5),
            'send_proposal'=>1,'requirements_reviewed'=>1,'reason'=>'Reviewed documents and priority; available slot offered.'], $overrides);
        $this->patch(route('admin.reservations.reschedule',$booking),previewedReschedule($booking,$data))->assertSessionHasNoErrors();
        return ScheduleProposal::latest('id')->firstOrFail();
    };
    $this->offerLink = fn ($proposal) => URL::temporarySignedRoute('reservation.schedule-proposal',$proposal->expires_at,['proposal'=>$proposal->id]);
});

test('equipment return records reduce usable stock without overwriting total inventory', function () {
    $this->actingAs($this->staff)->post(route('staff.reservations.equipment.return',[$this->past,$this->equipment]),$this->returnData)->assertSessionHasNoErrors();
    $record=EquipmentReturn::firstOrFail();
    expect($record->revision)->toBe(1)->and($record->recorded_by)->toBe($this->staff->id)
        ->and($this->equipment->refresh()->total_quantity)->toBe(10)->and($this->equipment->usableQuantity())->toBe(8);
    $future=($this->make)();
    expect(app(EquipmentRequestService::class)->available($this->equipment,$future))->toBe(8);
    $this->get(route('staff.reservations.show',$this->past))->assertOk()->assertSee('Return recorded');
});

test('return quantities must reconcile with issued and approved units', function ($changes) {
    $this->post($this->returnUrl,array_replace($this->returnData,$changes))->assertSessionHasErrors();
    expect(EquipmentReturn::count())->toBe(0)->and($this->equipment->usableQuantity())->toBe(10);
})->with([[['issued'=>6,'returned_good'=>4]],[['returned_good'=>2]],[['restored'=>3]],[['missing'=>-1]],[['damaged'=>'1.5']]]);

test('only admin can correct or restore stock and stale corrections are rejected', function () {
    $this->post($this->returnUrl,$this->returnData)->assertSessionHasNoErrors();
    $changes=[...$this->returnData,'restored'=>2,'revision'=>1,'correction_reason'=>'Repaired one chair and recovered the other.'];
    $this->actingAs($this->staff)->post(route('staff.reservations.equipment.return',[$this->past,$this->equipment]),$changes)->assertForbidden();
    $this->actingAs($this->admin)->post($this->returnUrl,[...$changes,'correction_reason'=>''])->assertSessionHasErrors('correction_reason');
    $this->post($this->returnUrl,$changes)->assertSessionHasNoErrors();
    expect($this->equipment->usableQuantity())->toBe(10)->and(EquipmentReturn::first()->revision)->toBe(2);
    $this->post($this->returnUrl,$changes)->assertSessionHasErrors('returns');
    expect($this->equipment->usableQuantity())->toBe(10);
});

test('future and cancelled bookings cannot record returns and missing allocations cannot be forged', function () {
    $future=($this->make)();
    $future->equipment()->attach($this->equipment,['quantity_requested'=>5,'quantity_approved'=>5]);
    $this->post(route('admin.reservations.equipment.return',[$future,$this->equipment]),$this->returnData)->assertSessionHasErrors('returns');
    $this->past->update(['status'=>'cancelled']);
    $this->post($this->returnUrl,$this->returnData)->assertSessionHasErrors('returns');
    $other=Equipment::create(['equipment_name'=>'Speaker','total_quantity'=>2,'status'=>'available']);
    $this->post(route('admin.reservations.equipment.return',[$this->past,$other]),$this->returnData)->assertNotFound();
});

test('physical incidents flag future approved allocations and block excess requests', function () {
    $this->post($this->returnUrl,$this->returnData)->assertSessionHasNoErrors();
    $future=($this->make)();
    $future->equipment()->attach($this->equipment,['quantity_requested'=>10,'quantity_approved'=>10,'status'=>'approved']);
    $this->get(route('admin.reservations.follow-up'))->assertOk()->assertSee('10 approved, 8 currently available');
    expect(fn()=>app(EquipmentRequestService::class)->assertAllocations($future))->toThrow(\Illuminate\Validation\ValidationException::class);
    expect($future->refresh()->status)->toBe('approved');
});

test('recorded returns protect historical schedules and allocations while allowing audited recovery after cancellation', function () {
    $this->post($this->returnUrl,$this->returnData)->assertSessionHasNoErrors();
    expect(fn()=>$this->past->update(['reservation_date'=>'2026-10-25']))->toThrow(\Illuminate\Validation\ValidationException::class);
    $this->past->refresh();
    $this->post(route('admin.reservations.equipment.review',[$this->past,$this->equipment]),['action'=>'reject','remarks'=>'Change allocation'])->assertSessionHasErrors('equipment');
    $this->past->update(['status'=>'cancelled']);
    $this->post($this->returnUrl,[...$this->returnData,'restored'=>2,'revision'=>1,'correction_reason'=>'Recovered and repaired after closure.'])->assertSessionHasNoErrors();
    expect($this->equipment->usableQuantity())->toBe(10);
});

test('a reviewed waiting-list offer confirms approval only after acceptance', function () {
    $waiting=($this->make)(['status'=>'waiting_list','reservation_date'=>'2026-10-24']);
    $proposal=($this->offer)($waiting);
    expect($proposal->kind)->toBe('waiting_list')->and($waiting->refresh()->status)->toBe('waiting_list')
        ->and($proposal->expires_at->toDateTimeString())->toBe('2026-10-19 12:00:00');
    $this->get(($this->offerLink)($proposal))->assertOk()->assertSee('Accept New Schedule');
    $this->post(($this->offerLink)($proposal),['decision'=>'accepted'])->assertSessionHasNoErrors();
    expect($waiting->refresh()->status)->toBe('approved')->and($waiting->approved_by)->toBe($this->admin->id)
        ->and($waiting->acknowledgementTask)->not->toBeNull();
    Mail::assertSent(ReservationScheduleChanged::class,fn($mail)=>$mail->outcome==='waiting_list_accepted' && str_contains($mail->render(),'now approved'));
});

test('declining a waiting offer leaves request waiting and releases no other booking', function () {
    $waiting=($this->make)(['status'=>'waiting_list','reservation_date'=>'2026-10-24']);
    $proposal=($this->offer)($waiting);
    $this->post(($this->offerLink)($proposal),['decision'=>'declined'])->assertSessionHasNoErrors();
    expect($waiting->refresh()->status)->toBe('waiting_list')->and($proposal->refresh()->status)->toBe('declined');
});

test('only one waiting offer can win a slot and acceptance never displaces the winner', function () {
    $one=($this->make)(['status'=>'waiting_list','reservation_date'=>'2026-10-24']);
    $two=($this->make)(['status'=>'waiting_list','reservation_date'=>'2026-10-24']);
    $first=($this->offer)($one);$second=($this->offer)($two);
    $this->post(($this->offerLink)($first),['decision'=>'accepted'])->assertSessionHasNoErrors();
    $this->post(($this->offerLink)($second),['decision'=>'accepted'])->assertSessionHasErrors('schedule');
    expect($one->refresh()->status)->toBe('approved')->and($two->refresh()->status)->toBe('waiting_list');
});

test('waiting offers require document review and restrictions are rechecked on acceptance', function () {
    $waiting=($this->make)(['status'=>'waiting_list','reservation_date'=>'2026-10-24']);
    $this->postJson(route('admin.reservations.reschedule-preview',$waiting),['reservation_date'=>'2026-10-24','start_time'=>'09:00','reason'=>'Offer','send_proposal'=>1])->assertUnprocessable()->assertJsonValidationErrors('requirements_reviewed');
    $proposal=($this->offer)($waiting);
    RequestorRestriction::create(['email'=>$waiting->contact_email,'reason'=>'Administrator review','expires_at'=>now()->addDays(5),'created_by'=>$this->admin->id]);
    $this->post(($this->offerLink)($proposal),['decision'=>'accepted'])->assertSessionHasErrors('contact_email');
    expect($waiting->refresh()->status)->toBe('waiting_list');
});

test('upcoming event reminders send once without changing booking status', function () {
    $booking=($this->make)();
    $service=app(ReservationFollowUpService::class);$row=$service->candidate('event',$booking->id);
    expect($row)->not->toBeNull();
    $request=['kind'=>'event','id'=>$booking->id,'fingerprint'=>$row['fingerprint']];
    $this->post(route('admin.reservations.follow-up.send'),$request)->assertSessionHasNoErrors();
    expect(ReservationReminder::first()->status)->toBe('sent')->and($booking->refresh()->status)->toBe('approved');
    $this->post(route('admin.reservations.follow-up.send'),$request)->assertSessionHasErrors('reminder');
    Mail::assertSentCount(1);Mail::assertSent(ReservationFollowUp::class,fn($mail)=>str_contains($mail->render(),'within 24 hours'));
});

test('stale reminders and ineligible requestor events are not emailed', function () {
    $booking=($this->make)();$service=app(ReservationFollowUpService::class);$row=$service->candidate('event',$booking->id);
    $booking->update(['status'=>'cancelled']);
    $this->post(route('admin.reservations.follow-up.send'),['kind'=>'event','id'=>$booking->id,'fingerprint'=>$row['fingerprint']])->assertSessionHasErrors('reminder');
    expect($service->candidate('event',$this->past->id))->toBeNull();
    expect($service->candidate('event',($this->make)(['status'=>'waiting_list'])->id))->toBeNull();
    expect($service->candidate('event',($this->make)(['contact_email'=>null])->id))->toBeNull();
    Mail::assertNothingSent();
});

test('missing document reminders stop when a permit is recorded', function () {
    $booking=($this->make)(['status'=>'new','agreement_accepted'=>true,'created_at'=>now()->subDays(2)]);
    $booking->forceFill(['created_at'=>now()->subDays(2)])->save();
    $service=app(ReservationFollowUpService::class);
    $row=$service->candidate('requirements',$booking->id);expect($row)->not->toBeNull();
    $booking->documents()->create(['document_type'=>'permit','file_path'=>'permits/test.pdf','original_filename'=>'test.pdf','stored_filename'=>'test.pdf','mime_type'=>'application/pdf','file_size'=>100]);
    expect($service->candidate('requirements',$booking->id))->toBeNull();
    $this->post(route('admin.reservations.follow-up.send'),['kind'=>'requirements','id'=>$booking->id,'fingerprint'=>$row['fingerprint']])->assertSessionHasErrors('reminder');
});

test('unanswered offers receive reminders only while valid and after the required interval', function () {
    $waiting=($this->make)(['status'=>'waiting_list','reservation_date'=>'2026-10-24']);
    $offer=($this->offer)($waiting);$service=app(ReservationFollowUpService::class);
    expect($service->candidate('proposal',$offer->id))->toBeNull();
    $this->travel(12)->hours();
    $row=$service->candidate('proposal',$offer->id);expect($row)->not->toBeNull();
    $this->post(route('admin.reservations.follow-up.send'),['kind'=>'proposal','id'=>$offer->id,'fingerprint'=>$row['fingerprint']])->assertSessionHasNoErrors();
    $offer->update(['status'=>'declined']);expect($service->candidate('proposal',$offer->id))->toBeNull();
});

test('failed reminder sends are recorded and retry is limited to once an hour', function () {
    $booking=($this->make)();$service=app(ReservationFollowUpService::class);$row=$service->candidate('event',$booking->id);
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('Test transport failure'));
    $request=['kind'=>'event','id'=>$booking->id,'fingerprint'=>$row['fingerprint']];
    $this->post(route('admin.reservations.follow-up.send'),$request)->assertSessionHasNoErrors();
    expect(ReservationReminder::first()->status)->toBe('failed');
    $this->post(route('admin.reservations.follow-up.send'),$request)->assertSessionHasErrors('reminder');
    $this->travel(61)->minutes();Mail::swap(new \Illuminate\Support\Testing\Fakes\MailFake(new \Illuminate\Mail\MailManager(app())));
    $this->post(route('admin.reservations.follow-up.send'),$request)->assertSessionHasNoErrors();
    expect(ReservationReminder::first()->status)->toBe('sent')->and(ReservationReminder::first()->attempts)->toBe(2);
});

test('staff cannot send reminders and follow-up page exposes actionable requests', function () {
    $waiting=($this->make)(['status'=>'waiting_list']);
    $this->get(route('admin.reservations.follow-up'))->assertOk()->assertSee('Review / offer '.$waiting->reference_number);
    $this->actingAs($this->staff)->get(route('admin.reservations.follow-up'))->assertForbidden();
    $this->post(route('admin.reservations.follow-up.send'),['kind'=>'event','id'=>$waiting->id,'fingerprint'=>str_repeat('a',64)])->assertForbidden();
});

test('outcome KPI goals remain unconfigured until explicitly reviewed and saved', function () {
    $scope=['year'=>2026];
    $this->get(route('admin.dashboard',$scope))->assertOk()->assertSee('Recorded Event Completion')->assertSee('Event Outcome Coverage');
    $this->put(route('admin.dashboard.kpi-targets'),['targets_reviewed'=>1,'kpi_event_completion'=>90,'kpi_no_show'=>5,'kpi_outcome_coverage'=>100])->assertSessionHasNoErrors();
    $cards=collect($this->get(route('admin.dashboard',$scope))->assertOk()->viewData('kpiCards'))->keyBy('key');
    expect($cards['event_completion']['target'])->toBe(90.0)->and($cards['no_show']['target'])->toBe(5.0)
        ->and($cards['event_completion']['value'])->toBeNull()->and($cards['outcome_coverage']['value'])->toBe(0.0);
});
