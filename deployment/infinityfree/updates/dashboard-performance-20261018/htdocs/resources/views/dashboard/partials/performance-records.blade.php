<div style="max-height:240px;overflow:auto"><ul aria-label="Supporting reservations">
    @forelse($evidenceRows as $evidence)
        <li><a href="{{ route('admin.reservations.show', $evidence) }}">{{ $evidence->reference_number }}</a> · {{ $evidence->reservation_date->format('M j, Y') }} · {{ ucwords(str_replace('_', ' ', $evidence->status)) }}</li>
    @empty<li>No matching reservations.</li>@endforelse
</ul></div>
