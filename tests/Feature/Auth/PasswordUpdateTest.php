<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('admin and staff can see the password form', function ($role) {
    $user = User::factory()->create(['role' => $role]);
    $this->actingAs($user)->get('/profile')->assertOk()
        ->assertSee('Update Password')->assertSee('Current Password');
})->with(['admin', 'staff']);

test('weak passwords are rejected without changing the account password', function ($password) {
    $user = User::factory()->create();
    $this->actingAs($user)->from('/profile')->put('/password', [
        'current_password' => 'password',
        'password' => $password,
        'password_confirmation' => $password,
    ])->assertSessionHasErrorsIn('updatePassword', 'password');
    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();
})->with(['Short!1a', 'lowercase!1234', 'UPPERCASE!1234', 'NoNumbers!Here', 'NoSymbols12345']);

test('password can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'password',
            'password' => 'FreshLogin!2026',
            'password_confirmation' => 'FreshLogin!2026',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->assertTrue(Hash::check('FreshLogin!2026', $user->refresh()->password));
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'wrong-password',
            'password' => 'FreshLogin!2026',
            'password_confirmation' => 'FreshLogin!2026',
        ]);

    $response
        ->assertSessionHasErrorsIn('updatePassword', 'current_password')
        ->assertRedirect('/profile');
});
