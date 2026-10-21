<section class="reference-panel mt-6" aria-labelledby="event-results-heading">
    <header><h2 id="event-results-heading">Event outcomes and actual attendance</h2><p>Recorded results for events that have ended, using the dashboard filters. Cancelled, rejected, unapproved and future events are excluded. Legacy completed statuses without a matching outcome record remain unverified.</p></header>
    <div class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4">
        @foreach(['Recorded completion rate' => $eventResults['completionRate'], 'Recorded no-show rate' => $eventResults['noShowRate'], 'Outcome recording coverage' => $eventResults['coverage'], 'Actual vs expected attendance' => $eventResults['attendanceRate']] as $title => $value)
            <div class="rounded-xl border border-slate-200 p-4"><h3 class="font-semibold">{{ $title }}</h3><p class="mt-3 text-2xl font-bold">{{ $value === null ? 'N/A' : $value.'%' }}</p></div>
        @endforeach
    </div>
</section>
