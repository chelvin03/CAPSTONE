<?php

use App\Mail\LoginVerificationCode;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->app->instance('env', 'local');
    config(['gym.auth.local_test_admin_password_only' => true]);
    // Local/production environment checks also enable CSRF validation in tests.
    $this->withSession(['_token' => 'local-admin-test-token'])
        ->withHeader('X-CSRF-TOKEN', 'local-admin-test-token');
});

test('local test admin can sign in with a password and access the dashboard', function () {
    $user = User::factory()->create(['email' => 'admin@mcst.edu.ph', 'role' => 'admin']);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('admin.dashboard'))->assertSessionMissing('login_challenge');

    $this->assertAuthenticatedAs($user);
    $this->get(route('admin.dashboard'))->assertOk();
    Mail::assertNothingSent();
});

test('local test admin still requires valid credentials and approval', function (string $status, string $password) {
    $user = User::factory()->create(['email' => 'admin@mcst.edu.ph', 'role' => 'admin', 'status' => $status]);

    $this->post('/login', ['email' => $user->email, 'password' => $password])->assertSessionHasErrors('email');

    $this->assertGuest();
    Mail::assertNothingSent();
})->with([
    ['approved', 'incorrect'],
    ['pending', 'password'],
    ['disabled', 'password'],
    ['rejected', 'password'],
]);

test('email verification remains required outside the enabled local test admin', function (string $environment, bool $enabled, string $email, string $role) {
    $this->app->instance('env', $environment);
    config(['gym.auth.local_test_admin_password_only' => $enabled]);
    $user = User::factory()->create(['email' => $email, 'role' => $role]);

    $this->post('/login', ['email' => $email, 'password' => 'password'])->assertRedirect(route('login.code'));

    $this->assertGuest();
    Mail::assertSent(LoginVerificationCode::class);
})->with([
    ['production', true, 'admin@mcst.edu.ph', 'admin'],
    ['local', false, 'admin@mcst.edu.ph', 'admin'],
    ['local', true, 'other@example.com', 'admin'],
    ['local', true, 'admin@mcst.edu.ph', 'staff'],
]);
