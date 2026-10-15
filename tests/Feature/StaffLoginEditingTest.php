<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('admin can update staff email and password without changing permissions', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $staff = User::factory()->create(['role' => 'staff', 'status' => 'approved']);
    $this->actingAs($admin)->get(route('admin.staff.index'))->assertOk()->assertSee('Edit Login');
    $this->get(route('admin.staff.edit', $staff))->assertOk()->assertSee('Your Current Admin Password');
    $this->put(route('admin.staff.update', $staff), [
        'email' => 'real-staff@example.com', 'current_password' => 'password',
        'password' => 'StaffNew!2026', 'password_confirmation' => 'StaffNew!2026',
        'role' => 'admin', 'status' => 'pending',
    ])->assertSessionHasNoErrors()->assertRedirect(route('admin.staff.index'));
    $staff->refresh();
    expect($staff->email)->toBe('real-staff@example.com')
        ->and(Hash::check('StaffNew!2026', $staff->password))->toBeTrue()
        ->and($staff->role)->toBe('staff')->and($staff->status)->toBe('approved')
        ->and($staff->email_verified_at)->toBeNull();
});

test('blank new password preserves the staff password', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $staff = User::factory()->create(['role' => 'staff']);
    $hash = $staff->password;
    $this->actingAs($admin)->put(route('admin.staff.update', $staff), [
        'email' => $staff->email, 'current_password' => 'password', 'password' => '',
    ])->assertSessionHasNoErrors();
    expect($staff->fresh()->password)->toBe($hash);
});

test('staff cannot edit login details and admin accounts cannot be targeted', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($staff)->get(route('admin.staff.edit', $staff))->assertForbidden();
    $this->put(route('admin.staff.update', $staff), [])->assertForbidden();
    $this->actingAs($admin)->get(route('admin.staff.edit', $admin))->assertNotFound();
    $this->put(route('admin.staff.update', $admin), [])->assertNotFound();
});

test('invalid credentials email or password leave staff unchanged', function ($overrides, $error) {
    $admin = User::factory()->create(['role' => 'admin']);
    $staff = User::factory()->create(['role' => 'staff']);
    $hash = $staff->password;
    $overrides = array_map(fn ($value) => $value === 'ADMIN_EMAIL' ? $admin->email : $value, $overrides);
    $this->actingAs($admin)->put(route('admin.staff.update', $staff), array_merge([
        'email' => $staff->email, 'current_password' => 'password',
        'password' => 'StaffNew!2026', 'password_confirmation' => 'StaffNew!2026',
    ], $overrides))->assertSessionHasErrors($error);
    expect($staff->fresh()->password)->toBe($hash)->and($staff->fresh()->email)->toBe($staff->email);
})->with([
    [['current_password' => 'wrong'], 'current_password'],
    [['email' => 'ADMIN_EMAIL'], 'email'],
    [['password' => 'weak', 'password_confirmation' => 'weak'], 'password'],
    [['password_confirmation' => 'Different!2026'], 'password'],
]);
