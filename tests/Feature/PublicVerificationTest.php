<?php

use App\Mail\PublicRegistrationCode;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(fn () => Mail::fake());

function publicOtpDetails(): array
{
    return ['email' => 'public@example.com', 'contact_person' => 'Public Requestor', 'contact_number' => '09123456789', 'reservation_type' => 'community'];
}

test('public verification sends a code and verifies only in its browser session', function () {
    $this->postJson('/reserve/send-code', publicOtpDetails())->assertOk();
    $code = Mail::sent(PublicRegistrationCode::class)->sole()->code;
    expect(session('public_email_challenge.hash'))->not->toBe($code);
    $pending = session('public_email_challenge');
    $this->withSession(['public_email_challenge' => null])
        ->postJson('/reserve/verify-code', ['email' => 'public@example.com', 'code' => $code])->assertStatus(422);
    $this->withSession(['public_email_challenge' => $pending])
        ->postJson('/reserve/verify-code', ['email' => 'public@example.com', 'code' => $code])
        ->assertOk()->assertJson(['verified' => true])->assertSessionMissing('public_email_challenge');
    $this->postJson('/reserve/verify-code', ['email' => 'public@example.com', 'code' => $code])->assertStatus(422);
    $this->assertGuest();
    expect(User::count())->toBe(0);
});

test('public codes expire and resending observes cooldown', function () {
    $this->postJson('/reserve/send-code', publicOtpDetails())->assertOk();
    $code = Mail::sent(PublicRegistrationCode::class)->sole()->code;
    $this->postJson('/reserve/send-code', publicOtpDetails())->assertStatus(429);
    $this->travel(11)->minutes();
    $this->postJson('/reserve/verify-code', ['email' => 'public@example.com', 'code' => $code])->assertStatus(422);
});

test('five incorrect public codes block further attempts', function () {
    $this->postJson('/reserve/send-code', publicOtpDetails())->assertOk();
    $code = Mail::sent(PublicRegistrationCode::class)->sole()->code;
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/reserve/verify-code', ['email' => 'public@example.com', 'code' => '000000'])->assertStatus(422);
    }
    $this->postJson('/reserve/verify-code', ['email' => 'public@example.com', 'code' => $code])->assertStatus(429);
});

test('mail failure leaves no public challenge', function () {
    Mail::shouldReceive('mailer')->with('smtp')->andThrow(new RuntimeException('Unavailable'));
    $this->postJson('/reserve/send-code', publicOtpDetails())
        ->assertStatus(503)->assertSessionMissing('public_email_challenge');
});

test('public details are validated before any email is sent', function () {
    $this->postJson('/reserve/send-code', ['email' => 'public@example.com'])
        ->assertUnprocessable()->assertJsonValidationErrors(['contact_person', 'contact_number', 'reservation_type']);
    Mail::assertNothingSent();
});

test('step three is rendered only for verified requests and details survive reload', function () {
    $this->get(route('reservation.create', ['step' => 3]))->assertRedirect(route('reservation.create'));
    $this->get(route('reservation.create'))->assertOk()->assertDontSee('name="event_name"', false);
    $this->postJson('/reserve/send-code', publicOtpDetails())->assertOk();
    $code = Mail::sent(PublicRegistrationCode::class)->sole()->code;
    Mail::assertSent(PublicRegistrationCode::class, fn ($mail) => $mail->hasTo('public@example.com') && $mail->mailer === 'smtp');
    expect(\Illuminate\Support\Facades\Hash::check($code, session('public_email_challenge.hash')))->toBeTrue();
    $this->postJson('/reserve/verify-code', ['email' => 'public@example.com', 'code' => $code])->assertOk();
    $this->get(route('reservation.create', ['step' => 3]))->assertOk()->assertSee('name="event_name"', false)->assertSee('Public Requestor');
    $this->postJson('/reserve/edit-details')->assertOk()->assertSessionMissing('public_email_verified');
    $this->get(route('reservation.create'))->assertOk()->assertSee('Public Requestor')->assertDontSee('name="event_name"', false);
    $this->get(route('reservation.create', ['step' => 3]))->assertRedirect(route('reservation.create'));
});

test('resend replaces the old code and limits repeated sends', function () {
    $this->postJson('/reserve/send-code', publicOtpDetails())->assertOk();
    $old = Mail::sent(PublicRegistrationCode::class)->last()->code;
    $this->travel(61)->seconds();
    $this->postJson('/reserve/send-code', publicOtpDetails())->assertOk();
    $fresh = Mail::sent(PublicRegistrationCode::class)->last()->code;
    expect($fresh)->not->toBe($old);
    $this->postJson('/reserve/verify-code', ['email' => 'public@example.com', 'code' => $old])
        ->assertUnprocessable()->assertJsonPath('message', 'Invalid verification code.');
    for ($i = 0; $i < 3; $i++) {
        $this->travel(61)->seconds();
        $this->postJson('/reserve/send-code', publicOtpDetails())->assertOk();
    }
    $this->travel(61)->seconds();
    $this->postJson('/reserve/send-code', publicOtpDetails())->assertStatus(429);
    Mail::assertSentCount(5);
});

test('expired and malformed codes give clear errors', function () {
    $this->postJson('/reserve/send-code', publicOtpDetails())->assertOk();
    $code = Mail::sent(PublicRegistrationCode::class)->sole()->code;
    foreach (['12345', '1234567', 'abcdef', ['123456']] as $invalid) {
        $this->postJson('/reserve/verify-code', ['email' => 'public@example.com', 'code' => $invalid])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
    }
    $this->travel(11)->minutes();
    $this->postJson('/reserve/verify-code', ['email' => 'public@example.com', 'code' => $code])
        ->assertUnprocessable()->assertJsonPath('message', 'Your verification code has expired. Please request a new code.');
});

test('changed email invalidates prior verification and must be verified again', function () {
    $this->postJson('/reserve/send-code', publicOtpDetails())->assertOk();
    $code = Mail::sent(PublicRegistrationCode::class)->sole()->code;
    $this->postJson('/reserve/verify-code', ['email' => 'public@example.com', 'code' => $code])->assertOk();
    $this->postJson('/reserve/send-code', [...publicOtpDetails(), 'email' => 'new@example.com'])
        ->assertOk()->assertSessionMissing('public_email_verified');
    $this->get(route('reservation.create', ['step' => 3]))->assertRedirect(route('reservation.create'));
    $this->postJson('/reserve/verify-code', ['email' => 'new@example.com', 'code' => $code])->assertUnprocessable();
    $fresh = Mail::sent(PublicRegistrationCode::class)->last()->code;
    $this->postJson('/reserve/verify-code', ['email' => 'new@example.com', 'code' => $fresh])->assertOk();
});

test('expired verified proof cannot access step three', function () {
    $this->withSession([
        'public_email_verified' => ['email' => 'public@example.com', 'verified' => true, 'expires_at' => now()->subSecond()->timestamp],
        'public_reservation_details' => ['contact_email' => 'public@example.com'],
    ])->get(route('reservation.create', ['step' => 3]))->assertRedirect(route('reservation.create'));
});

test('mail exceptions are logged without credentials or message contents', function () {
    \Illuminate\Support\Facades\Log::spy();
    Mail::shouldReceive('mailer')->with('smtp')->andThrow(new RuntimeException('535 authenticate secret-password 123456'));
    $this->postJson('/reserve/send-code', publicOtpDetails())->assertStatus(503)
        ->assertDontSee('secret-password')->assertDontSee('123456');
    \Illuminate\Support\Facades\Log::shouldHaveReceived('error')->once()->withArgs(function ($message, $context) {
        return $context['reason'] === 'smtp_authentication_failed'
            && $context['exception_class'] === RuntimeException::class
            && ! str_contains(json_encode($context), 'secret-password')
            && ! str_contains(json_encode($context), '123456');
    });
});
