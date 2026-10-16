<section class="card mt-6 p-6" aria-labelledby="ar-preview-title">
    <h2 id="ar-preview-title" class="font-bold">Reservation Preview</h2>
    <dl class="mt-4 space-y-2">
    @foreach(['Reservation Reference' => $previewBooking['reference'], 'Requestor' => $previewBooking['requestor'], 'Event Name' => $previewBooking['event'], 'Event Type' => \App\Services\DashboardAnalyticsService::label($previewBooking['event_type'] ?? null), 'Facility' => $previewBooking['facility'], 'Reservation Date' => $previewBooking['date'], 'Time' => $previewBooking['start'].' – '.$previewBooking['end'], 'Expected Attendees' => number_format($previewBooking['participants'])] as $caption => $value)
        <div><dt class="text-sm text-slate-500">{{ $caption }}</dt><dd>{{ $value }}</dd></div>
    @endforeach
    </dl>
    @if($previewBooking['equipment'])<h3 class="mt-4 font-semibold">Approved Equipment</h3><ul>@foreach($previewBooking['equipment'] as $item)<li>{{ $item['name'] }}: {{ $item['quantity'] }}</li>@endforeach</ul>@endif
    <p class="mt-4 text-sm text-slate-500">This receipt acknowledges the approved gymnasium reservation.</p>
</section>
