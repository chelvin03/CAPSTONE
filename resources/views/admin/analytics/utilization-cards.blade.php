@php
    $facilityCards = [
        ['Available Facility Hours', $format($availableHours), 'bi-calendar3', 'available'],
        ['Approved Booked Hours', $format($utilizedHours), 'bi-check-circle-fill', 'booked'],
        ['Scheduled Utilization', $format($utilizationRate, '%'), 'bi-percent', 'utilization'],
        ['Actual Usage', 'Not Yet Available', 'bi-clock', 'actual'],
        ['Unused Booked Hours', $format($unusedHours), 'bi-x-circle-fill', 'unused'],
        ['Peak Usage Period', $facilityWorkspace['peakLabel'], 'bi-bar-chart', 'peak'],
    ];
@endphp
<div class="analytics-cards facility-cards">@foreach($facilityCards as [$title, $value, $icon, $key])
    <article class="analytics-stat facility-stat facility-stat-{{ $key }}"><i class="bi {{ $icon }}" aria-hidden="true"></i><div><h2>{{ $title }}</h2>
    @if($key === 'actual')
        <i class="bi bi-info-circle facility-info" aria-label="Actual usage is not recorded"></i>
        <div class="facility-unavailable"><i class="bi bi-file-earmark-clock" aria-hidden="true"></i><span>{{ $value }}</span><small>Pending event completion</small></div>
    @else
        <strong>{{ $value }}</strong>
        @if($key === 'peak')<p>{{ $facilityWorkspace['peakHours'] }} booked hours</p>
        @else
            @php($change = $facilityWorkspace['changes'][$key])
            <p class="facility-change {{ $key === 'unused' && $change > 0 ? 'facility-change-bad' : '' }}">@if($change === null)<span>—</span>@else<i class="bi {{ $change < 0 ? 'bi-arrow-down' : 'bi-arrow-up' }}" aria-hidden="true"></i> {{ abs($change) }}%@endif</p>
            <p>{{ $change === null ? 'No previous data' : $facilityWorkspace['comparisonLabel'] }}</p>
        @endif
    @endif
    </div></article>
@endforeach</div>
