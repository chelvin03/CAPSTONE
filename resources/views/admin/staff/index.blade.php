@extends('layouts.app')

@section('title', 'Staff Accounts')

@section('content')
<div class="mx-auto max-w-7xl">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm font-semibold text-blue-600">Administration</p>
            <h1 class="text-3xl font-bold text-slate-900">Staff Accounts</h1>
            <p class="mt-1 text-sm text-slate-500">View staff members and create their login accounts.</p>
        </div>
        <a href="{{ route('admin.staff.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700">+ Add Staff</a>
    </div>

    @if (session('success'))
        <div class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
    @endif

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <form method="GET" action="{{ route('admin.staff.index') }}" class="flex flex-col gap-3 border-b border-slate-200 p-5 sm:flex-row">
            <input type="search" name="search" value="{{ $search }}" placeholder="Search name or email" class="min-h-11 flex-1 rounded-lg border border-slate-300 px-4 text-sm focus:border-blue-500 focus:ring-4 focus:ring-blue-100">
            <button class="min-h-11 rounded-lg bg-slate-900 px-5 text-sm font-semibold text-white hover:bg-slate-800">Search</button>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50"><tr>
                    @foreach (['Name', 'Email', 'Contact Number', 'Status', 'Priority Permission', 'Created'] as $heading)
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $heading }}</th>
                    @endforeach
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($staff as $member)
                        <tr>
                            <td class="px-6 py-4 text-sm font-semibold text-slate-900">{{ $member->full_name }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $member->email }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $member->contact_number ?: '—' }}</td>
                            <td class="px-6 py-4"><span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800">{{ ucfirst($member->status) }}</span></td>
                            <td class="px-6 py-4"><form method="POST" action="{{route('admin.staff.permissions',$member)}}">@csrf @method('PATCH')<label class="flex items-center gap-2 text-xs"><input type="checkbox" name="can_priority_override" value="1" @checked($member->can_priority_override) onchange="this.form.submit()"> Authorized</label></form></td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $member->created_at->format('M d, Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-10 text-center text-sm text-slate-500">No staff accounts found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($staff->hasPages())
            <div class="border-t border-slate-200 p-5">{{ $staff->links() }}</div>
        @endif
    </div>
</div>
@endsection
