<?php

use App\Models\User;
use Illuminate\Support\Facades\Password;

test('admin can save recovery email without changing login and invalidates old reset token', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $token = Password::createToken($user);
    $this->actingAs($user)->patch('/profile', [
        'first_name' => $user->first_name, 'last_name' => $user->last_name,
        'email' => $user->email, 'recovery_email' => 'real@example.com', 'recovery_password' => 'password',
    ])->assertSessionHasNoErrors();
    expect($user->fresh()->email)->toBe($user->email);
    expect($user->fresh()->recovery_email)->toBe('real@example.com');
    expect(Password::tokenExists($user->fresh(), $token))->toBeFalse();
});

test('recovery email changes require current password and are admin only', function () {
    foreach (['admin', 'staff'] as $role) {
        $user = User::factory()->create(['role' => $role]);
        $this->actingAs($user)->patch('/profile', [
            'first_name' => $user->first_name, 'last_name' => $user->last_name,
            'email' => $user->email, 'recovery_email' => 'real@example.com',
        ])->assertSessionHasErrors($role === 'admin' ? 'recovery_password' : 'recovery_email');
        expect($user->fresh()->recovery_email)->toBeNull();
    }
});
