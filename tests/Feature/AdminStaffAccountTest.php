<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('admin can create a verified staff account', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->post(route('admin.staff.store'), [
            'first_name' => 'Jamie',
            'last_name' => 'Santos',
            'email' => 'jamie.santos@example.com',
            'contact_number' => '09123456789',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ])
        ->assertRedirect(route('admin.staff.index'))
        ->assertSessionHas('success');

    $staff = User::where('email', 'jamie.santos@example.com')->firstOrFail();

    expect($staff->role)->toBe('staff')
        ->and($staff->status)->toBe('approved')
        ->and($staff->email_verified_at)->not->toBeNull()
        ->and(Hash::check('SecurePassword123!', $staff->password))->toBeTrue();
});

test('non admins cannot access staff account management', function () {
    $staff = User::factory()->create(['role' => 'staff']);

    $this->actingAs($staff)
        ->get(route('admin.staff.index'))
        ->assertForbidden();
});
