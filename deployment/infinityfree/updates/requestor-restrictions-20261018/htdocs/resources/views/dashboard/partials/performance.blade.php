<section class="decision-support performance-analysis" aria-labelledby="performance-heading">
    <header><div><p class="decision-eyebrow">MEASURE • COMPARE • INVESTIGATE</p><h2 id="performance-heading">Operational performance analytics</h2><p>Uses the dashboard filters and scheduled event dates. Expand a result to inspect its supporting reservations.</p></div></header>
    <div class="decision-grid">
        <article><h3>Gym utilization by month</h3>
            <p>Approved scheduled hours ÷ bookable operating hours. Overlaps are merged; administrator blocks are excluded. This measures scheduled use, not actual attendance. Cleaning time is not event use.</p>
            @if($performance['trendLimited'])<p class="decision-note">Monthly charts show only the last 12 months of the selected range. Summary cards retain the full range.</p>@endif
            @foreach($performance['months'] as $month)
                <details><summary>{{ $month['label'] }} — {{ $month['utilizationRate'] === null ? 'N/A' : $month['utilizationRate'].'%' }} utilization</summary>
                    <p>{{ $month['utilizedHours'] ?? 'N/A' }} booked hours / {{ $month['availableHours'] ?? 'N/A' }} bookable hours.</p>
                    @include('dashboard.partials.performance-records', ['evidenceRows' => $month['records']->where('status', 'approved')])
                </details>
                <meter min="0" max="100" value="{{ $month['utilizationRate'] ?? 0 }}" aria-label="{{ $month['label'] }} utilization" style="width:100%">{{ $month['utilizationRate'] ?? 'N/A' }}%</meter>
            @endforeach
            <div class="decision-action"><strong>Decision supported</strong><p>Compare available capacity across months before adding events. Check the existing weekday heatmap and Schedule before choosing a maintenance interval.</p></div>
        </article>
        <article><h3>Reservation processing and review delays</h3>
            <p><strong>Median: {{ $performance['processingMedian'] === null ? 'N/A' : $performance['processingMedian'].' hours' }}</strong> · Average: {{ $performance['processingAverage'] === null ? 'N/A' : $performance['processingAverage'].' hours' }}.</p>
            <p>Submission to first recorded approval, rejection or cancellation. {{ $performance['processingSample'] }} valid records; {{ $performance['processingMissing'] }} without a valid decision timestamp are excluded. Pending age is measured as of now.</p>
            <p>Ever approved: {{ $performance['approvalRate'] === null ? 'N/A' : $performance['approvalRate'].'%' }} · Currently rejected: {{ $performance['rejectionRate'] === null ? 'N/A' : $performance['rejectionRate'].'%' }} of selected requests. These are separate measures, not complementary rates.</p>
            @foreach(['bands' => 'Decision time', 'pending' => 'Pending age'] as $group => $caption)
                <h4>{{ $caption }}</h4>
                @foreach($performance[$group] as $bucket => $rows)
                    <details><summary>{{ $bucket }} — {{ $rows->count() }} requests</summary>@include('dashboard.partials.performance-records', ['evidenceRows' => $rows])</details>
                @endforeach
            @endforeach
            <div class="decision-action"><strong>Decision supported</strong><p>Inspect older pending requests and long decision times to identify missing requirements and review delays. A long elapsed time alone does not establish the cause.</p></div>
        </article>
        <article><h3>Cancellation trend and recorded causes</h3>
            <p>Cancelled requests ÷ all selected requests, grouped by scheduled event month, including future events. N/A means no matching requests.</p>
            @foreach($performance['months'] as $month)
                <details><summary>{{ $month['label'] }} — {{ $month['cancellationRate'] === null ? 'N/A' : $month['cancellationRate'].'%' }} cancelled</summary>@include('dashboard.partials.performance-records', ['evidenceRows' => $month['records']->where('status', 'cancelled')])</details>
                <meter min="0" max="100" value="{{ $month['cancellationRate'] ?? 0 }}" aria-label="{{ $month['label'] }} cancellation rate" style="width:100%"></meter>
            @endforeach
            <h4>Who cancelled?</h4>
            @foreach($performance['sources'] as $source => $rows)
                <details><summary>{{ $source }} — {{ $rows->count() }} cancellations</summary>@include('dashboard.partials.performance-records', ['evidenceRows' => $rows])</details>
            @endforeach
            <p>Requestor cancellations use the explicit status-history marker. Administrator cancellations use the recorded staff account. Missing attribution remains unknown.</p>
        </article>
        <article><h3>Cancellation reasons to investigate</h3>
            <p><a class="btn-primary" href="{{ route('admin.requestor-restrictions.index') }}">Review frequent cancellations and restrictions</a></p>
            <p>Grouped by the exact recorded reason. Free-text reasons are not automatically interpreted as proven causes.</p>
            @forelse($performance['reasons'] as $reason => $rows)
                <details><summary>{{ $reason }} — {{ $rows->count() }} cancellations</summary>@include('dashboard.partials.performance-records', ['evidenceRows' => $rows])</details>
            @empty<p>No cancelled reservations match the selected filters.</p>@endforelse
            <div class="decision-action"><strong>Decision supported</strong><p>Review repeated reasons before changing reminders or scheduling rules. Separate administrator displacement from requestor cancellations when evaluating improvements.</p></div>
        </article>
    </div>
</section>
