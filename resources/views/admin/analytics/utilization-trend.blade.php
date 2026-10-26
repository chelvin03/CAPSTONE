@php
    $trendRows = collect($rows)->values();
    $trendMax = max(1, $trendRows->max('availableHours') ?? 1, $trendRows->max('utilizedHours') ?? 1);
    $trendStep = 520 / max(1, $trendRows->count() - 1);
    $trendX = fn ($index) => $trendRows->count() === 1 ? 320 : 60 + $index * $trendStep;
@endphp
<div class="analytics-legend"><span><i style="background:#287cf0"></i>Available Hours</span><span><i style="background:#31b47a"></i>Booked Hours</span><span><i style="background:#c8e0ff"></i>Utilization Rate (%)</span></div>
<svg class="analytics-chart facility-trend" viewBox="0 0 640 270" role="img" aria-label="Scheduled utilization trend: facility hours and utilization rate percentage">
    @foreach(range(0, 5) as $tick)
        <line x1="60" x2="580" y1="{{ 210 - $tick * 34 }}" y2="{{ 210 - $tick * 34 }}" stroke="#e7eef7"/>
        <text x="50" y="{{ 214 - $tick * 34 }}" text-anchor="end">{{ round($trendMax * $tick / 5, 1) }}</text><text x="590" y="{{ 214 - $tick * 34 }}">{{ $tick * 20 }}%</text>
    @endforeach
    <text transform="translate(14 125) rotate(-90)" text-anchor="middle">Hours</text><text transform="translate(635 125) rotate(-90)" text-anchor="middle">Utilization Rate (%)</text>
    @foreach($trendRows as $index => $row)
        @if($row['utilizationRate'] !== null && $index > 0 && $trendRows[$index - 1]['utilizationRate'] !== null)
            <polygon points="{{ $trendX($index - 1) }},210 {{ $trendX($index - 1) }},{{ 210 - $trendRows[$index - 1]['utilizationRate'] * 1.7 }} {{ $trendX($index) }},{{ 210 - $row['utilizationRate'] * 1.7 }} {{ $trendX($index) }},210" fill="#c8e0ff" opacity=".65"/>
        @endif
    @endforeach
    @foreach([['availableHours', '#287cf0'], ['utilizedHours', '#31b47a']] as [$key, $color])
        @foreach($trendRows as $index => $row)
            @if($row[$key] !== null)
                @if($index > 0 && $trendRows[$index - 1][$key] !== null)<line x1="{{ $trendX($index - 1) }}" y1="{{ 210 - $trendRows[$index - 1][$key] / $trendMax * 170 }}" x2="{{ $trendX($index) }}" y2="{{ 210 - $row[$key] / $trendMax * 170 }}" stroke="{{ $color }}" stroke-width="3"/>@endif
                <circle cx="{{ $trendX($index) }}" cy="{{ 210 - $row[$key] / $trendMax * 170 }}" r="4" fill="{{ $color }}"><title>{{ $row['label'] }}: {{ $row[$key] }} hours; utilization {{ $row['utilizationRate'] ?? 'Not available' }}%</title></circle>
            @endif
        @endforeach
    @endforeach
    @foreach($trendRows as $index => $row)<text x="{{ $trendX($index) }}" y="230" text-anchor="end" transform="rotate(-25 {{ $trendX($index) }} 230)">{{ $row['label'] }}</text>@endforeach
</svg>
