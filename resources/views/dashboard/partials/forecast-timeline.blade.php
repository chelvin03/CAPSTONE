<?php
    $timeline = $monthly->map(fn ($row) => $row + ['estimated' => false])->values();
    if ($estimate !== null) foreach ($forecast as $row) $timeline->push($row + ['estimated' => true]);
    $timelineMaximum = max(20,ceil(max(1,$timeline->max('count'))/20)*20);
    $timelineStep = 1040 / max(1,$timeline->count());
    $forecastStart = 55+12*$timelineStep;
?>
<div class="bottom-chart-legend forecast-chart-legend"><span><i class="legend-total"></i>Recorded Reservations</span><span><i class="legend-rate-line"></i>Forecasted Reservations (Estimated)</span></div>
@if($estimate === null)<p class="admin-chart-note">Insufficient history for a forecast. Recorded reservations are shown below.</p>@endif
<div class="bottom-chart-scroll"><svg class="bottom-chart-svg" viewBox="0 0 1140 235" role="img" aria-label="Recorded reservations in selected period, followed by separately labeled upcoming demand estimates">
    @if($estimate !== null)<rect x="{{ $forecastStart }}" y="25" width="{{ 1095-$forecastStart }}" height="155" fill="#fff3f4"/>@endif
    @foreach(range(0,4) as $tick)<line x1="55" x2="1095" y1="{{ 180-$tick*37.5 }}" y2="{{ 180-$tick*37.5 }}" class="chart-grid"/><text x="43" y="{{ 184-$tick*37.5 }}" text-anchor="end" class="chart-axis">{{ $timelineMaximum*$tick/4 }}</text>@endforeach
    <line x1="55" x2="55" y1="30" y2="180" class="chart-baseline"/>
    @foreach($timeline as $i => $row)
        <?php $x=55+($i+.5)*$timelineStep; $height=$row['count']/$timelineMaximum*150; ?>
        @unless($row['estimated'])<rect x="{{ $x-14 }}" y="{{ 180-$height }}" width="28" height="{{ $height }}" rx="2" class="chart-booking-bar"/>@endunless
        <text x="{{ $x }}" y="{{ 171-$height }}" text-anchor="middle" class="{{ $row['estimated'] ? 'timeline-estimate-count' : 'chart-count' }}">{{ $row['count'] }}</text><text x="{{ $x }}" y="201" text-anchor="middle" class="chart-axis">{{ $row['label'] }}</text>
        @if($row['estimated'])<circle cx="{{ $x }}" cy="{{ 180-$height }}" r="4" fill="#ef4444"><title>{{ $row['label'] }}: {{ $row['count'] }} estimated reservations</title></circle>@endif
    @endforeach
    @if($estimate !== null)
        <line x1="{{ $forecastStart }}" x2="{{ $forecastStart }}" y1="25" y2="213" stroke="#f3c5c8"/>
        <polyline points="@foreach($timeline as $i => $row)@if($row['estimated']){{ 55+($i+.5)*$timelineStep }},{{ 180-$row['count']/$timelineMaximum*150 }} @endif @endforeach" fill="none" stroke="#ef4444" stroke-width="2" stroke-dasharray="7 5"/>
        <text x="{{ 55+6*$timelineStep }}" y="225" text-anchor="middle" class="chart-axis">Selected period: {{ $selectedYear === 'all' ? 'All years' : $selectedYear }}</text><text x="{{ 55+13.5*$timelineStep }}" y="225" text-anchor="middle" class="timeline-estimate-count">Upcoming estimates</text>
    @endif
    <text x="15" y="110" transform="rotate(-90 15 110)" text-anchor="middle" class="chart-axis">Number of Reservations</text>
</svg></div>