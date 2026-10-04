<div x-show="navigationOpen" x-transition.opacity class="fixed inset-0 z-40 bg-slate-950/50 lg:hidden" @click="navigationOpen = false" aria-hidden="true"></div>
<aside id="mobile-navigation"
    :class="navigationOpen ? 'translate-x-0' : '-translate-x-full'"
    class="fixed inset-y-0 left-0 z-50 flex h-screen min-h-screen w-72 shrink-0 flex-col overflow-y-auto text-white transition-transform lg:sticky lg:top-0 lg:z-20 lg:translate-x-0"
    style="background-color: #136be7;"
>
    {{-- Logo --}}
    <div class="relative border-b border-white/10 px-6 py-6">
        <button type="button" @click="navigationOpen = false" class="absolute right-3 top-3 rounded-lg p-2 text-blue-100 hover:bg-white/10 lg:hidden" aria-label="Close navigation"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
        <a href="{{ route(auth()->user()?->role === 'staff' ? 'staff.dashboard' : 'admin.dashboard') }}"
           class="flex items-center gap-3">

            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-white/10">
                <span class="text-xl font-bold">M</span>
            </div>

            <div>
                <p class="text-xl font-bold leading-tight text-white">
                    MCST Gym
                </p>

                <p class="text-sm text-blue-200">
                    Reservation System
                </p>
            </div>
        </a>
    </div>

    {{-- Navigation --}}
    <nav class="flex-1 space-y-1 px-4 py-6">

        <p class="px-3 pb-2 text-sm font-semibold uppercase tracking-wider text-blue-300">
            Main
        </p>

        @if (auth()->user()?->role === 'staff')
            @foreach ([
                ['staff.dashboard', 'Dashboard', 'bi-grid'],
                ['staff.reservations.index', 'Reservations', 'bi-calendar-check'],
                ['staff.schedules.index', 'Schedules', 'bi-calendar3'],
                ['staff.facilities.index', 'Facilities', 'bi-building'],
                ['staff.equipment.index', 'Equipment', 'bi-tools'],
            ] as [$destination, $label, $icon])
                <a href="{{ route($destination) }}" class="flex items-center gap-3 rounded-lg px-3 py-3 text-base font-medium transition {{ request()->routeIs(str_replace('.index', '.*', $destination)) ? 'bg-white/10 text-white' : 'text-blue-100 hover:bg-white/10 hover:text-white' }}">
                    <i class="bi {{ $icon }}" aria-hidden="true"></i><span>{{ $label }}</span>
                </a>
            @endforeach
        @else

        {{-- Dashboard --}}
        <a
            href="{{ route('admin.dashboard') }}"
            class="flex items-center gap-3 rounded-lg px-3 py-3 text-base font-medium transition
                {{ request()->routeIs('admin.dashboard')
                    ? 'bg-white/10 text-white'
                    : 'text-blue-100 hover:bg-white/10 hover:text-white' }}"
        >
            <i class="bi bi-house" aria-hidden="true"></i>
            <span>Dashboard</span>
        </a>

        {{-- Reservations --}}
        <a
            href="{{ route('admin.reservations.index') }}"
            class="flex items-center gap-3 rounded-lg px-3 py-3 text-base font-medium transition
                {{ request()->routeIs('admin.reservations.*')
                    ? 'bg-white/10 text-white'
                    : 'text-blue-100 hover:bg-white/10 hover:text-white' }}"
        >
            <i class="bi bi-calendar-check" aria-hidden="true"></i>
            <span>Reservations</span>
        </a>
        <a href="{{ route('admin.schedule.index') }}" class="flex items-center gap-3 rounded-lg px-3 py-3 text-base font-medium transition {{ request()->routeIs('admin.schedule.*') ? 'bg-white/10 text-white' : 'text-blue-100 hover:bg-white/10 hover:text-white' }}"><i class="bi bi-calendar3" aria-hidden="true"></i><span>Schedule</span></a>

        {{-- Equipment --}}
        <a
            href="{{ route('admin.equipment.index') }}"
            class="flex items-center gap-3 rounded-lg px-3 py-3 text-base font-medium transition
                {{ request()->routeIs('admin.equipment.*')
                    ? 'bg-white/10 text-white'
                    : 'text-blue-100 hover:bg-white/10 hover:text-white' }}"
        >
            <i class="bi bi-tools" aria-hidden="true"></i>
            <span>Equipment</span>
        </a>

        {{-- User Management --}}
        <a
            href="{{ route('admin.staff.index') }}"
            class="flex items-center gap-3 rounded-lg px-3 py-3 text-base font-medium transition
                {{ request()->routeIs('admin.staff.*')
                    ? 'bg-white/10 text-white'
                    : 'text-blue-100 hover:bg-white/10 hover:text-white' }}"
        >
            <i class="bi bi-people" aria-hidden="true"></i>
            <span>User Management</span>
        </a>

        {{-- Reports --}}
        @if(Route::has('admin.reports.index'))
            <a
                href="{{ route('admin.reports.index') }}"
                class="flex items-center gap-3 rounded-lg px-3 py-3 text-base font-medium transition
                    {{ request()->routeIs('admin.reports.*')
                        ? 'bg-white/10 text-white'
                        : 'text-blue-100 hover:bg-white/10 hover:text-white' }}"
            >
                <i class="bi bi-bar-chart" aria-hidden="true"></i>
                <span>Reports</span>
            </a>
        @else
            <span
                class="flex cursor-not-allowed items-center gap-3 rounded-lg px-3 py-3 text-base font-medium text-blue-300 opacity-60"
                title="Reports module is not created yet"
            >
                <i class="bi bi-bar-chart" aria-hidden="true"></i>
                <span>Reports</span>
            </span>
        @endif
        <a href="{{ route('admin.audit.index') }}" class="flex items-center gap-3 rounded-lg px-3 py-3 text-base font-medium transition {{ request()->routeIs('admin.audit.*') ? 'bg-white/10 text-white' : 'text-blue-100 hover:bg-white/10 hover:text-white' }}"><i class="bi bi-journal-text" aria-hidden="true"></i><span>Audit Logs</span></a>
        <a href="{{ route('admin.backups.index') }}" class="flex items-center gap-3 rounded-lg px-3 py-3 text-base font-medium transition {{ request()->routeIs('admin.backups.*') ? 'bg-white/10 text-white' : 'text-blue-100 hover:bg-white/10 hover:text-white' }}"><i class="bi bi-database-check" aria-hidden="true"></i><span>Backup & Recovery</span></a>
        <div class="grid grid-cols-1 gap-1">
            <a href="{{ route('admin.settings.edit') }}" class="flex items-center gap-2 rounded-lg px-3 py-3 text-base font-medium transition {{ request()->routeIs('admin.settings.*') ? 'bg-white/10 text-white' : 'text-blue-100 hover:bg-white/10 hover:text-white' }}"><i class="bi bi-gear" aria-hidden="true"></i><span>Settings</span></a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-3 text-left text-base font-medium text-blue-100 transition hover:bg-red-500/20 hover:text-white">
                    <i class="bi bi-box-arrow-right" aria-hidden="true"></i><span>Logout</span>
                </button>
            </form>
        </div>

        @endif

    </nav>

    @if (auth()->user()?->role === 'staff')
    <div class="border-t border-white/10 p-4">
        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button
                type="submit"
                class="flex w-full items-center gap-3 rounded-lg px-3 py-3 text-left text-base font-medium text-blue-100 transition hover:bg-red-500/20 hover:text-white"
            >
                <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                <span>Logout</span>
            </button>
        </form>
    </div>
    @endif

</aside>
