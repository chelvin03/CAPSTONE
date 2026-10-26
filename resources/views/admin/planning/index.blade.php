@extends('layouts.app')
@section('title', 'Planning Schedule')
@section('content')
<link rel="stylesheet" href="{{ asset('css/planning-schedule.css') }}?v={{ filemtime(public_path('css/planning-schedule.css')) }}">
<div class="planning-workspace">
    <header class="planning-heading"><h1>Planning Schedule</h1><p>MCST Main Gymnasium · Today + Next 3 Days</p></header>
    @if($errors->any())<div class="planning-error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <div class="planning-stats">@foreach([['Upcoming Activities', $total], ['Ready for Event', $readyCount], ['Preparation Needed', $preparationCount], ['Requires Attention', $attention->count()]] as [$title, $value])<article><h2>{{ $title }}</h2><strong>{{ $value }}</strong></article>@endforeach</div>
    <div class="planning-days">@foreach($days as $day)<section class="planning-day"><header><h2>{{ $day['label'] }}</h2><p>{{ $day['date']->format('M j') }}</p></header>
        @forelse($day['activities'] as $activity)
            @php($booking = $activity['reservation'])
            <article class="planning-activity" @if($selected && $selected['reservation']->id === $booking->id) aria-current="true" @endif>
                <p class="planning-time">{{ \Carbon\Carbon::parse($booking->start_time)->format('g:i A') }}–{{ substr($booking->end_time, 0, 5) === '24:00' ? '12:00 AM' : \Carbon\Carbon::parse($booking->end_time)->format('g:i A') }}</p>
                <h3><a href="{{ route('admin.planning.index', ['activity' => $booking->id]) }}#planning-checklist" aria-label="Prepare {{ $booking->event_name }}, {{ $booking->reservation_date->format('M j') }}">{{ $booking->event_name }}</a></h3>
                <p class="planning-state {{ $activity['ready'] ? 'ready' : ($booking->status !== 'approved' ? 'pending' : ($activity['reasons'] ? 'attention' : 'preparation')) }}">{{ $activity['ready'] ? 'Ready' : ($booking->status !== 'approved' ? 'Pending' : ($activity['reasons'] ? 'Attention' : 'Preparation')) }}</p>
            </article>
        @empty<p class="planning-empty">No confirmed or pending activities.</p>@endforelse
    </section>@endforeach</div>
    <div class="planning-bottom"><section class="planning-panel" id="planning-checklist" @if($selected) aria-label="Preparation Checklist for {{ $selected['reservation']->event_name }} on {{ $selected['reservation']->reservation_date->format('M j') }}" @endif><header><h2>Preparation Checklist</h2><span class="planning-pill">{{ $selected ? $selected['tasks']->where('completed', false)->count() : 0 }} pending</span></header>
        @if($selected)
            @foreach($selected['tasks'] as $task)<form method="POST" action="{{ route('admin.planning.update', $selected['reservation']) }}" class="planning-check-row">@csrf @method('PATCH')<input type="hidden" name="task" value="{{ $task['key'] }}"><input type="hidden" name="snapshot" value="{{ $selected['snapshot'] }}"><input type="hidden" name="completed" value="0"><label><input type="checkbox" name="completed" value="1" @checked($task['completed']) onchange="this.form.requestSubmit()" aria-label="{{ $task['label'] }} for {{ $selected['reservation']->event_name }}"><span>{{ $task['label'] }}</span></label><noscript><button>Save</button></noscript></form>@endforeach
        @else<p class="planning-empty">No activities need preparation.</p>@endif
    </section><section class="planning-panel"><header><h2>Activities Requiring Attention</h2><span class="planning-pill alert">{{ $attention->count() }} {{ $attention->count() === 1 ? 'item' : 'items' }}</span></header>
        @forelse($attention as $activity)<article class="planning-attention-item"><h3><a href="{{ route('admin.reservations.show', $activity['reservation']) }}">{{ $activity['reservation']->event_name }} · {{ $activity['reservation']->reservation_date->format('M j') }}</a></h3><p>{{ implode(' ', $activity['reasons']) }}</p></article>@empty<p class="planning-empty">No activities currently require attention.</p>@endforelse
    </section></div>
</div>
@endsection