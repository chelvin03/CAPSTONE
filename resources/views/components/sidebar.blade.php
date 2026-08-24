<div x-show="navigationOpen" x-transition.opacity class="fixed inset-0 z-40 bg-slate-950/50 lg:hidden" @click="navigationOpen = false" aria-hidden="true"></div>
<aside id="mobile-navigation"
    :class="navigationOpen ? 'translate-x-0' : '-translate-x-full'"
    class="fixed inset-y-0 left-0 z-50 flex min-h-screen w-64 shrink-0 flex-col text-white transition-transform lg:sticky lg:top-0 lg:z-20 lg:translate-x-0"
    style="background-color: #136be7;"
>
    {{-- Logo --}}
    <div class="relative border-b border-white/10 px-6 py-6">
        <button type="button" @click="navigationOpen = false" class="absolute right-3 top-3 rounded-lg p-2 text-blue-100 hover:bg-white/10 lg:hidden" aria-label="Close navigation"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
        <a href="{{ route(auth()->user()?->role === 'staff' ? 'staff.dashboard' : 'admin.dashboard') }}"
           class="flex items-center gap-3">

            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-white/10">
                <span class="text-lg font-bold">M</span>
            </div>

            <div>
                <p class="text-lg font-bold leading-tight text-white">
                    MCST Gym
                </p>

                <p class="text-xs text-blue-200">
                    Reservation System
                </p>
            </div>
        </a>
    </div>

    {{-- Navigation --}}
    <nav class="flex-1 space-y-1 px-4 py-6">

        <p class="px-3 pb-2 text-xs font-semibold uppercase tracking-wider text-blue-300">
            Main
        </p>

        @if (auth()->user()?->role === 'staff')
            <a
                href="{{ route('staff.dashboard') }}"
                class="flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium transition {{ request()->routeIs('staff.dashboard') ? 'bg-white/10 text-white' : 'text-blue-100 hover:bg-white/10 hover:text-white' }}"
            >
                <i class="bi bi-grid" aria-hidden="true"></i>
                <span>Dashboard</span>
            </a>

            <a
                href="{{ route('staff.reservations.index') }}"
                class="flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium transition {{ request()->routeIs('staff.reservations.*') ? 'bg-white/10 text-white' : 'text-blue-100 hover:bg-white/10 hover:text-white' }}"
            >
                <i class="bi bi-calendar-check" aria-hidden="true"></i>
                <span>Reservations</span>
            </a>

            <a
                href="{{ route('staff.facilities.index') }}"
                class="flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium transition {{ request()->routeIs('staff.facilities.*') ? 'bg-white/10 text-white' : 'text-blue-100 hover:bg-white/10 hover:text-white' }}"
            >
                <i class="bi bi-building" aria-hidden="true"></i>
                <span>Facilities</span>
            </a>

            <a
                href="{{ route('staff.equipment.index') }}"
                class="flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium transition {{ request()->routeIs('staff.equipment.*') ? 'bg-white/10 text-white' : 'text-blue-100 hover:bg-white/10 hover:text-white' }}"
            >
                <i class="bi bi-tools" aria-hidden="true"></i>
                <span>Equipment</span>
            </a>
        @else

        {{-- Dashboard --}}
        <a
            href="{{ route('admin.dashboard') }}"
            class="flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium transition
                {{ request()->routeIs('admin.dashboard')
                    ? 'bg-white/10 text-white'
                    : 'text-blue-100 hover:bg-white/10 hover:text-white' }}"
        >
            <span class="flex h-5 w-5 items-center justify-center">🏠</span>
            <span>Dashboard</span>
        </a>

        {{-- Reservations --}}
        <a
            href="{{ route('admin.reservations.index') }}"
            class="flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium transition
                {{ request()->routeIs('admin.reservations.*')
                    ? 'bg-white/10 text-white'
                    : 'text-blue-100 hover:bg-white/10 hover:text-white' }}"
        >
            <span class="flex h-5 w-5 items-center justify-center">📅</span>
            <span>Reservations</span>
        </a>

        {{-- Facilities --}}
        <a
            href="{{ route('admin.facilities.index') }}"
            class="flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium transition
                {{ request()->routeIs('admin.facilities.*')
                    ? 'bg-white/10 text-white'
                    : 'text-blue-100 hover:bg-white/10 hover:text-white' }}"
        >
            <span class="flex h-5 w-5 items-center justify-center">🏢</span>
            <span>Facilities</span>
        </a>

        {{-- Equipment --}}
        <a
            href="{{ route('admin.equipment.index') }}"
            class="flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium transition
                {{ request()->routeIs('admin.equipment.*')
                    ? 'bg-white/10 text-white'
                    : 'text-blue-100 hover:bg-white/10 hover:text-white' }}"
        >
            <span class="flex h-5 w-5 items-center justify-center">🧰</span>
            <span>Equipment</span>
        </a>

        {{-- Announcements --}}
        @if(Route::has('admin.announcements.index'))
            <a
                href="{{ route('admin.announcements.index') }}"
                class="flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium transition
                    {{ request()->routeIs('admin.announcements.*')
                        ? 'bg-white/10 text-white'
                        : 'text-blue-100 hover:bg-white/10 hover:text-white' }}"
            >
                <span class="flex h-5 w-5 items-center justify-center">📢</span>
                <span>Announcements</span>
            </a>
        @else
            <span
                class="flex cursor-not-allowed items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium text-blue-300 opacity-60"
                title="Announcements module is not created yet"
            >
                <span class="flex h-5 w-5 items-center justify-center">📢</span>
                <span>Announcements</span>
            </span>
        @endif

        {{-- Reports --}}
        @if(Route::has('admin.reports.index'))
            <a
                href="{{ route('admin.reports.index') }}"
                class="flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium transition
                    {{ request()->routeIs('admin.reports.*')
                        ? 'bg-white/10 text-white'
                        : 'text-blue-100 hover:bg-white/10 hover:text-white' }}"
            >
                <span class="flex h-5 w-5 items-center justify-center">📊</span>
                <span>Reports</span>
            </a>
        @else
            <span
                class="flex cursor-not-allowed items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium text-blue-300 opacity-60"
                title="Reports module is not created yet"
            >
                <span class="flex h-5 w-5 items-center justify-center">📊</span>
                <span>Reports</span>
            </span>
        @endif

        @endif

    </nav>

    {{-- Logout --}}
    <div class="border-t border-white/10 p-4">
        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button
                type="submit"
                class="flex w-full items-center gap-3 rounded-lg px-3 py-3 text-left text-sm font-medium text-blue-100 transition hover:bg-red-500/20 hover:text-white"
            >
                <span>↪</span>
                <span>Logout</span>
            </button>
        </form>
    </div>

</aside>
