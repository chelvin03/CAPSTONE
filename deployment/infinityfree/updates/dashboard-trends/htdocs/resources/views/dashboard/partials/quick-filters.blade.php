<nav class="mb-5 flex flex-wrap gap-3" aria-label="Dashboard quick date filters">
    @foreach(['today' => 'Today', 'month' => 'This Month', 'year' => 'This Year'] as $period => $periodLabel)
        <a href="{{ route('admin.dashboard', ['period' => $period]) }}" class="{{ request('period') === $period ? 'btn-primary' : 'btn-secondary' }}" @if(request('period') === $period) aria-current="true" @endif>{{ $periodLabel }}</a>
    @endforeach
</nav>
