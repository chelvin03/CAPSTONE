<?php

use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
});
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

test('both roles sign in directly even without SMTP', function (string $role) {
    config(['mail.mailers.smtp.transport' => 'unavailable']);
    $user = User::factory()->create(['role' => $role]);
    $sessionId = session()->getId();
    $this->withSession(['login_challenge' => ['user_id' => $user->id]])
        ->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => true])
        ->assertRedirect(route($role.'.dashboard'))
        ->assertSessionMissing('login_challenge')
        ->assertCookie(\Illuminate\Support\Facades\Auth::guard('web')->getRecallerName());
    $this->assertAuthenticatedAs($user);
    expect(session()->getId())->not->toBe($sessionId);
    expect(\App\Models\AuditLog::where('action', 'authentication.login')->count())->toBe(1);
    Mail::assertNothingSent();
    $this->get(route($role.'.dashboard'))->assertOk();
    $this->get(route(($role === 'admin' ? 'staff' : 'admin').'.dashboard'))->assertForbidden();
})->with(['admin', 'staff']);

test('retired code endpoints do not send mail or authenticate', function () {
    $this->get('/login/code')->assertNotFound();
    foreach (['/login/code', '/login/code/resend', '/login/code/cancel'] as $url) {
        $this->post($url, ['code' => '123456'])->assertNotFound();
    }
    $this->assertGuest();
    Mail::assertNothingSent();
});
test('five wrong passwords temporarily block login', function () {
    $user = User::factory()->create();
    for ($i = 0; $i < 5; $i++) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertSessionHasErrors('email');
    }
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();
    $this->travel(61)->seconds();
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('staff.dashboard'));
    $this->assertAuthenticatedAs($user);
});
