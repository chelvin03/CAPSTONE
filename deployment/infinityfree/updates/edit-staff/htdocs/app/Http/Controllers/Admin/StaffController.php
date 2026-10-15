<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $staff = User::query()
            ->where('role', 'staff')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.staff.index', compact('staff', 'search'));
    }

    public function create(): View
    {
        return view('admin.staff.create');
    }

    public function edit(User $staff): View
    {
        abort_unless($staff->role === 'staff', 404);

        return view('admin.staff.edit', compact('staff'));
    }

    public function update(Request $request, User $staff): RedirectResponse
    {
        abort_unless($staff->role === 'staff', 404);

        $validated = $request->validate([
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staff->id)],
            'current_password' => ['required', 'current_password'],
            'password' => ['nullable', 'confirmed', Rules\Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);

        $staff->email = $validated['email'];
        if ($staff->isDirty('email')) {
            $staff->email_verified_at = null;
        }
        if (! empty($validated['password'])) {
            $staff->password = Hash::make($validated['password']);
        }
        if ($staff->isDirty(['email', 'password'])) {
            $staff->setRememberToken(Str::random(60));
        }
        $staff->save();

        return redirect()->route('admin.staff.index')->with('success', 'Staff login details updated successfully.');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'contact_number' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        User::create([
            ...$validated,
            'password' => Hash::make($validated['password']),
            'role' => 'staff',
            'status' => 'approved',
            'email_verified_at' => now(),
        ]);

        return redirect()
            ->route('admin.staff.index')
            ->with('success', 'Staff account created successfully.');
    }

    public function updatePermissions(Request $request, User $staff): RedirectResponse
    {
        abort_unless($staff->role === 'staff', 404);
        $staff->update(['can_priority_override' => $request->boolean('can_priority_override')]);
        AuditLog::record('staff.permissions_updated', $staff, ['can_priority_override' => $staff->can_priority_override]);

        return back()->with('success', 'Staff permissions updated.');
    }
}
