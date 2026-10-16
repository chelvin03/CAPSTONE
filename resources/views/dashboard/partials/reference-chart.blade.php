@php
    $chartRows = collect($rows)->values();
    $chartMax = max(1, $chartRows->max('count'));
    $chartTick = $chartMax <= 4 ? 1 : ($chartMax <= 8 ? 2 : max(5, ceil($chartMax / 20) * 5));
    $chartTop = $chartTick * 4;
    $chartWidth = $chartWidth ?? 460;
    $plotWidth = $chartWidth - 70;
    $chartStep = $plotWidth / max(1, $chartRows->count());
    $isForecast = $isForecast ?? false;
    if ($isForecast) $chartTop = max(10, ceil($chartMax / 10) * 10);
    $tickCount = $isForecast ? 5 : 4;
    $withRates = $withRates ?? false;
    $rateTop = max(50, ceil(($chartRows->max('rate') ?? 0) / 10) * 10);
@endphp
<svg class="admin-chart" viewBox="0 0 {{ $chartWidth }} 220" role="img" aria-label="{{ $chartLabel }}">
    @foreach(range(0,$tickCount) as $tick)
        <?php $y = 180 - $tick * 150 / $tickCount; ?>
        <line x1="35" x2="{{ $chartWidth - 35 }}" y1="{{ $y }}" y2="{{ $y }}" class="chart-grid"/>
        <text x="24" y="{{ $y + 4 }}" text-anchor="end" class="chart-axis">{{ $chartTop * $tick / $tickCount }}</text>
    @endforeach
    <line x1="35" x2="35" y1="30" y2="180" class="chart-baseline"/>
    @foreach($chartRows as $i => $row)
        @php
            $x = 35 + ($i + .5) * $chartStep;
            $height = $row['count'] / $chartTop * 150;
            $barWidth = min($isForecast ? 64 : 30, $chartStep * .65);
        @endphp
        <rect x="{{ $x - $barWidth / 2 }}" y="{{ 180 - $height }}" width="{{ $barWidth }}" height="{{ $height }}" rx="3" class="{{ $isForecast ? 'chart-estimate-bar' : 'chart-booking-bar' }}"><title>{{ $row['label'] }}: {{ $row['count'] }} {{ $isForecast ? 'estimated reservations' : 'reservations' }}</title></rect>
        <text x="{{ $x }}" y="{{ 180 - $height - 8 }}" text-anchor="middle" class="chart-count">{{ $row['count'] }}</text>
        <text x="{{ $x }}" y="201" text-anchor="middle" class="chart-axis">{{ $row['label'] }}</text>
    @endforeach
    @if($withRates)
        @foreach(range(0,5) as $rateTick)<text x="{{ $chartWidth - 23 }}" y="{{ 184 - $rateTick * 30 }}" class="chart-rate-axis">{{ $rateTop * $rateTick / 5 }}%</text>@endforeach
        <polyline points="@foreach($chartRows as $i => $row){{ 35 + ($i + .5) * $chartStep }},{{ 180 - $row['rate'] / $rateTop * 150 }} @endforeach" fill="none" stroke="#ef4444" stroke-width="2"/>
        @foreach($chartRows as $i => $row)<circle cx="{{ 35 + ($i + .5) * $chartStep }}" cy="{{ 180 - $row['rate'] / $rateTop * 150 }}" r="3" fill="#ef4444"><title>{{ $row['label'] }}: {{ $row['cancelled'] }} cancelled ({{ $row['rate'] }}%)</title></circle>@endforeach
    @endif
</svg>
