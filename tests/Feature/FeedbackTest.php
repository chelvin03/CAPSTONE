<?php

use App\Mail\FeedbackInvitation;
use App\Models\Feedback;
use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    Mail::fake();
    $this->admin = User::factory()->create(['role' => 'admin']);
    $gym = Facility::create(['facility_name' => 'MCST Gymnasium', 'capacity' => 2000, 'status' => 'available']);
    $this->booking = Reservation::create(['reference_number' => 'FEEDBACK-TEST', 'facility_id' => $gym->id,
        'reservation_type' => 'external', 'event_name' => 'Event', 'purpose' => 'Practice', 'contact_person' => 'Requestor',
        'contact_number' => '09123456789', 'contact_email' => 'requestor@example.com', 'expected_attendees' => 40,
        'reservation_date' => now()->subDay()->toDateString(), 'start_time' => '09:00', 'end_time' => '11:00', 'status' => 'approved']);
});

test('requestor receives signed link after gym use and admin receives feedback', function () {
    $this->get('/feedback')->assertOk()->assertSee('Share Your Feedback');
    $this->post('/feedback', ['reference_number' => $this->booking->reference_number, 'email' => $this->booking->contact_email])->assertSessionHas('status');
    $mail = Mail::sent(FeedbackInvitation::class)->sole();
    expect($mail->hasTo($this->booking->contact_email))->toBeTrue();
    $this->get($mail->feedbackUrl)->assertOk()->assertSee('How Was Your Gym Experience?');
    $this->post($mail->feedbackUrl, ['rating' => 5, 'comments' => 'Very clean gym.'])->assertRedirect(route('public.feedback'));
    $this->assertDatabaseHas('feedback', ['reservation_id' => $this->booking->id, 'user_id' => null, 'rating' => 5]);
    $this->actingAs($this->admin)->get('/admin/feedback')->assertOk()->assertSee('Very clean gym.')->assertSee('5.0 / 5');
});

test('wrong email upcoming and rejected reservations receive no feedback link', function () {
    $this->post('/feedback', ['reference_number' => $this->booking->reference_number, 'email' => 'wrong@example.com'])->assertSessionHas('status');
    $this->booking->update(['reservation_date' => now()->addDay()->toDateString()]);
    $this->post('/feedback', ['reference_number' => $this->booking->reference_number, 'email' => $this->booking->contact_email]);
    $this->booking->update(['reservation_date' => now()->subDay()->toDateString(), 'status' => 'rejected']);
    $this->post('/feedback', ['reference_number' => $this->booking->reference_number, 'email' => $this->booking->contact_email]);
    Mail::assertNothingSent();
});

test('signature and expiry are enforced and feedback cannot be submitted twice', function () {
    $url = URL::temporarySignedRoute('public.feedback.form', now()->addHour(), ['reservation' => $this->booking->id]);
    $this->post('/feedback/submit/'.$this->booking->id, ['rating' => 5])->assertForbidden();
    $this->post($url, ['rating' => 6])->assertSessionHasErrors('rating');
    $this->post($url, ['rating' => 4, 'comments' => '<script>alert(1)</script>'])->assertSessionHasNoErrors();
    $this->post($url, ['rating' => 1])->assertSessionHasErrors('rating');
    expect(Feedback::count())->toBe(1);
    $this->travel(61)->minutes();
    $this->get($url)->assertForbidden();
});

test('feedback eligibility is rechecked if reservation is cancelled', function () {
    $url = URL::temporarySignedRoute('public.feedback.form', now()->addHour(), ['reservation' => $this->booking->id]);
    $this->booking->update(['status' => 'cancelled']);
    $this->post($url, ['rating' => 5])->assertSessionHasErrors('rating');
    $this->assertDatabaseCount('feedback', 0);
});

test('staff cannot access feedback inbox and comments are escaped', function () {
    Feedback::create(['reservation_id' => $this->booking->id, 'rating' => 3, 'comments' => '<script>alert(1)</script>']);
    $this->get('/admin/feedback')->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create(['role' => 'staff']))->get('/admin/feedback')->assertForbidden();
    $this->actingAs($this->admin)->get('/admin/feedback')->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
});
