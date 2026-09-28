<?php

use App\Mail\LoginVerificationCode;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->app->instance('env', 'local');
    config(['gym.auth.local_test_staff_password_only' => true]);
    $this->withSession(['_token' => 'staff-test-token'])->withHeader('X-CSRF-TOKEN', 'staff-test-token');
});

test('local test staff signs in without email and retains staff permissions', function () {
    $user = User::factory()->create(['email' => 'staff@mcst.edu.ph', 'role' => 'staff']);
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('staff.dashboard'))->assertSessionMissing('login_challenge');
    $this->assertAuthenticatedAs($user);
    $this->get(route('staff.dashboard'))->assertOk();
    $this->get(route('admin.dashboard'))->assertForbidden();
    Mail::assertNothingSent();
});

test('local test staff still requires password and approval', function (string $status, string $password) {
    $user = User::factory()->create(['email' => 'staff@mcst.edu.ph', 'role' => 'staff', 'status' => $status]);
    $this->post('/login', ['email' => $user->email, 'password' => $password])->assertSessionHasErrors('email');
    $this->assertGuest();
    Mail::assertNothingSent();
})->with([['approved', 'incorrect'], ['pending', 'password'], ['disabled', 'password'], ['rejected', 'password']]);

test('staff email verification remains required outside the exact enabled local account', function (string $environment, bool $enabled, string $email, string $role) {
    $this->app->instance('env', $environment);
    config(['gym.auth.local_test_staff_password_only' => $enabled]);
    $user = User::factory()->create(['email' => $email, 'role' => $role]);
    $this->post('/login', ['email' => $email, 'password' => 'password'])->assertRedirect(route('login.code'));
    $this->assertGuest();
    Mail::assertSent(LoginVerificationCode::class);
})->with([
    ['production', true, 'staff@mcst.edu.ph', 'staff'],
    ['local', false, 'staff@mcst.edu.ph', 'staff'],
    ['local', true, 'other@example.com', 'staff'],
    ['local', true, 'staff@mcst.edu.ph', 'admin'],
]);
