<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

test('reset notification goes to recovery email and keeps login email in link', function () {
    $user = User::factory()->create(['role' => 'admin', 'recovery_email' => 'real@example.com']);
    $notification = new ResetPassword('test-token');
    expect($user->routeNotificationFor('mail', $notification))->toBe('real@example.com');
    expect($notification->toMail($user)->actionUrl)->toContain(urlencode($user->email));
});

test('staff and admins without recovery email receive no reset notification', function () {
    Notification::fake();
    $staff = User::factory()->create(['recovery_email' => 'staff@example.com']);
    $admin = User::factory()->create(['role' => 'admin']);
    foreach ([$staff->email, $admin->email, 'missing@example.com'] as $email) {
        $this->post('/forgot-password', ['email' => $email])->assertSessionHas('status');
    }
    Notification::assertNothingSent();
});

test('staff cannot reset password even with a broker token', function () {
    $staff = User::factory()->create();
    $token = \Illuminate\Support\Facades\Password::createToken($staff);
    $this->post('/reset-password', [
        'email' => $staff->email, 'token' => $token,
        'password' => 'changed-password', 'password_confirmation' => 'changed-password',
    ])->assertSessionHasErrors('email');
    expect(\Illuminate\Support\Facades\Hash::check('password', $staff->fresh()->password))->toBeTrue();
});

test('reset password link screen can be rendered', function () {
    $response = $this->get('/forgot-password');

    $response->assertStatus(200);
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create(['role' => 'admin', 'recovery_email' => 'recovery@example.com']);

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create(['role' => 'admin', 'recovery_email' => 'recovery@example.com']);

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $response = $this->get('/reset-password/'.$notification->token);

        $response->assertStatus(200);

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create(['role' => 'admin', 'recovery_email' => 'recovery@example.com']);

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });
});

