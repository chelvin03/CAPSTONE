<?php

use App\Mail\LoginVerificationCode;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;

beforeEach(function () {
    Mail::fake();
});

function beginCodeLogin($test, User $user, bool $remember = false): string
{
    $test->post(route('login'), ['email' => $user->email, 'password' => 'password', 'remember' => $remember])
        ->assertRedirect(route('login.code'));

    return Mail::sent(LoginVerificationCode::class)->last()->code;
}

test('password alone grants no access and the code is never stored or displayed as plaintext', function (string $role) {
    $user = User::factory()->create(['role' => $role]);
    $code = beginCodeLogin($this, $user, true);
    $this->assertGuest();
    expect($code)->toMatch('/^[0-9]{6}$/');
    Mail::assertSent(LoginVerificationCode::class, fn ($mail) => $mail->hasTo($user->email) && $mail->mailer === 'smtp');
    expect(AuditLog::where('action', 'authentication.login')->count())->toBe(0);
    foreach (['admin.dashboard', 'staff.dashboard', 'admin.reservations.index', 'staff.reservations.index', 'profile.edit'] as $route) {
        $this->get(route($route))->assertRedirect(route('login'));
    }
    $this->get(route('login.code'))->assertOk()->assertDontSee($code)->assertSee('Verification code');
    $challenge = Cache::get('login-code:challenge:'.$user->id);
    expect($challenge)->not->toHaveKey('code');
    expect(Hash::check(hash_hmac('sha256', $code, config('app.key')), $challenge['hash']))->toBeTrue();
    expect(session('login_challenge'))->not->toHaveKey('code');
    $sessionId = session()->getId();

    $this->post(route('login.code.verify'), ['code' => $code])
        ->assertRedirect(route($role.'.dashboard'))->assertSessionMissing('login_challenge');
    $this->assertAuthenticatedAs($user);
    expect(session()->getId())->not->toBe($sessionId);
    expect(Cache::get('login-code:challenge:'.$user->id))->toBeNull();
    expect(AuditLog::where('action', 'authentication.login')->count())->toBe(1);
    $this->get(route($role.'.dashboard'))->assertOk();
    $this->get(route(($role === 'admin' ? 'staff' : 'admin').'.dashboard'))->assertForbidden();
})->with(['admin', 'staff']);

test('non approved accounts retain their existing messages and receive no code', function ($status, $message) {
    $user = User::factory()->create(['status' => $status]);
    $this->from(route('login'))->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => $message]);
    $this->assertGuest();
    Mail::assertNothingSent();
})->with([
    ['pending', 'Your account is still pending administrator approval.'],
    ['rejected', 'Your account registration was rejected.'],
    ['disabled', 'Your account has been disabled.'],
]);

test('wrong passwords and unsupported roles cannot request a code', function () {
    $user = User::factory()->create(['role' => 'requestor']);
    $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => 'This account does not have permission to access the system.']);
    Mail::assertNothingSent();
    $this->assertGuest();
});

test('five incorrect attempts lock the account across resends and fresh password logins', function () {
    $user = User::factory()->create();
    $code = beginCodeLogin($this, $user);
    $wrong = $code === '000000' ? '000001' : '000000';
    for ($i = 0; $i < 5; $i++) {
        $this->from(route('login.code'))->post(route('login.code.verify'), ['code' => $wrong])
            ->assertSessionHasErrors('code')->assertSessionMissing('_old_input.code');
    }
    $this->post(route('login.code.verify'), ['code' => $code])->assertSessionHasErrors('code');
    $this->travel(61)->seconds();
    $this->post(route('login.code.resend'))->assertSessionHasErrors('code');
    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('code');
    Mail::assertSentCount(1);
    $this->assertGuest();
    $this->travel(10)->minutes();
    $fresh = beginCodeLogin($this, $user);
    $this->post(route('login.code.verify'), ['code' => $fresh])->assertRedirect(route('staff.dashboard'));
});

test('expired codes cannot log in but can be replaced after ten minutes', function () {
    $user = User::factory()->create();
    $code = beginCodeLogin($this, $user);
    $this->travel(10)->minutes();
    $this->post(route('login.code.verify'), ['code' => $code])->assertSessionHasErrors('code');
    $this->assertGuest();
    $this->post(route('login.code.resend'))->assertRedirect(route('login.code'))->assertSessionHasNoErrors();
    $fresh = Mail::sent(LoginVerificationCode::class)->last()->code;
    $this->post(route('login.code.verify'), ['code' => $fresh])->assertRedirect(route('staff.dashboard'));
});

test('resends enforce cooldown and total limits and invalidate the previous code', function () {
    $user = User::factory()->create();
    $old = beginCodeLogin($this, $user);
    $this->post(route('login.code.resend'))->assertSessionHasErrors('code');
    Mail::assertSentCount(1);
    $this->travel(61)->seconds();
    $this->post(route('login.code.resend'))->assertSessionHasNoErrors();
    $fresh = Mail::sent(LoginVerificationCode::class)->last()->code;
    expect($fresh)->not->toBe($old);
    $this->post(route('login.code.verify'), ['code' => $old])->assertSessionHasErrors('code');
    for ($i = 0; $i < 3; $i++) {
        $this->travel(61)->seconds();
        $this->post(route('login.code.resend'))->assertSessionHasNoErrors();
    }
    $this->travel(61)->seconds();
    $this->post(route('login.code.resend'))->assertSessionHasErrors('code');
    Mail::assertSentCount(5);
    $this->assertGuest();
});

test('verification and resending recheck account status and credentials', function (array $change, string $endpoint) {
    $user = User::factory()->create();
    $code = beginCodeLogin($this, $user);
    $user->update($change);
    $this->travel(61)->seconds();
    $this->post(route($endpoint), ['code' => $code])
        ->assertRedirect(route('login'))->assertSessionHasErrors('email')->assertSessionMissing('login_challenge');
    $this->assertGuest();
    Mail::assertSentCount(1);
})->with([
    'disabled' => [['status' => 'disabled']],
    'role revoked' => [['role' => 'requestor']],
    'password changed' => [['password' => 'changed-password']],
    'email changed' => [['email' => 'changed@example.com']],
])->with(['login.code.verify', 'login.code.resend']);

test('missing expired cancelled and different browser challenges cannot log in', function () {
    $this->get(route('login.code'))->assertRedirect(route('login'));
    $this->post(route('login.code.verify'), ['code' => '123456'])->assertRedirect(route('login'));
    $this->post(route('login.code.resend'))->assertRedirect(route('login'));
    $user = User::factory()->create();
    $code = beginCodeLogin($this, $user);
    $pending = session('login_challenge');
    $this->withSession(['login_challenge' => array_merge($pending, ['token' => 'different-browser'])])
        ->post(route('login.code.verify'), ['code' => $code])->assertSessionHasErrors('code');
    $this->withSession(['login_challenge' => $pending])->post(route('login.code.cancel'))->assertRedirect(route('login'));
    expect(Cache::get('login-code:challenge:'.$user->id))->toBeNull();
    $this->withSession(['login_challenge' => $pending])->post(route('login.code.verify'), ['code' => $code])->assertSessionHasErrors('code');
    $this->travel(31)->minutes();
    $this->post(route('login.code.resend'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('a consumed code cannot be replayed with an old pending session', function () {
    $user = User::factory()->create();
    $code = beginCodeLogin($this, $user);
    $pending = session('login_challenge');
    $this->post(route('login.code.verify'), ['code' => $code])->assertRedirect(route('staff.dashboard'));
    $this->post(route('logout'));
    Auth::forgetGuards();
    $this->withSession(['login_challenge' => $pending])->post(route('login.code.verify'), ['code' => $code])->assertSessionHasErrors('code');
    $this->assertGuest();
});

test('SMTP failure leaves no usable code or authenticated session', function () {
    $user = User::factory()->create();
    Mail::shouldReceive('mailer')->with('smtp')->once()->andReturnSelf();
    Mail::shouldReceive('to')->with($user->email)->once()->andReturnSelf();
    Mail::shouldReceive('send')->once()->andThrow(new TransportException('SMTP unavailable'));
    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('code')->assertSessionMissing('login_challenge');
    expect(Cache::get('login-code:challenge:'.$user->id))->toBeNull();
    $this->assertGuest();
});

test('verification rejects malformed codes without reflecting them into session input', function () {
    $user = User::factory()->create();
    beginCodeLogin($this, $user);
    foreach (['12345', '1234567', 'abcdef', ['123456']] as $input) {
        $this->post(route('login.code.verify'), ['code' => $input])
            ->assertSessionHasErrors('code')->assertSessionMissing('_old_input.code');
    }
    $this->assertGuest();
});
