@php
    $chartRows = collect($rows)->values();
    $maximum = max(1, $chartRows->flatMap(fn ($row) => collect($series)->map(fn ($s) => $row[$s[0]] ?? 0))->max() ?? 1);
    $step = 560 / max(1, $chartRows->count());
@endphp
<div class="analytics-legend">@foreach($series as [$key, $caption, $color])<span><i style="background:{{ $color }}"></i>{{ $caption }}</span>@endforeach</div>
<svg class="analytics-chart" viewBox="0 0 640 260" role="img" aria-label="{{ $chartTitle }}">
    @if($showBarValues ?? false)<text transform="translate(14 125) rotate(-90)" text-anchor="middle">{{ $series[0][1] }}</text>@endif
    @foreach(range(0, 4) as $tick)<line x1="55" x2="615" y1="{{ 210 - $tick * 43 }}" y2="{{ 210 - $tick * 43 }}" stroke="#e7eef7"/><text x="45" y="{{ 214 - $tick * 43 }}" text-anchor="end">{{ round($maximum * $tick / 4, 1) }}</text>@endforeach
    @foreach($series as $seriesIndex => [$key, $caption, $color])
        @if(($kind ?? 'line') === 'line')
            @foreach($chartRows as $index => $row)
                @if(($row[$key] ?? null) !== null)
                    @if($index > 0 && ($chartRows[$index - 1][$key] ?? null) !== null)<line x1="{{ 55 + ($index - .5) * $step }}" y1="{{ 210 - $chartRows[$index - 1][$key] / $maximum * 172 }}" x2="{{ 55 + ($index + .5) * $step }}" y2="{{ 210 - $row[$key] / $maximum * 172 }}" stroke="{{ $color }}" stroke-width="3"/>@endif
                    <circle cx="{{ 55 + ($index + .5) * $step }}" cy="{{ 210 - $row[$key] / $maximum * 172 }}" r="4" fill="{{ $color }}"><title>{{ $row['label'] }} · {{ $caption }}: {{ $row[$key] }}</title></circle>
                @endif
            @endforeach
        @else
            @foreach($chartRows as $index => $row)<rect x="{{ 55 + $index * $step + $step * .12 + $seriesIndex * $step * .72 / count($series) }}" y="{{ 210 - ($row[$key] ?? 0) / $maximum * 172 }}" width="{{ $step * .68 / count($series) }}" height="{{ ($row[$key] ?? 0) / $maximum * 172 }}" rx="3" fill="{{ $color }}"><title>{{ $row['label'] }} · {{ $caption }}: {{ $row[$key] ?? 'Not available' }}</title></rect>@if($showBarValues ?? false)<text x="{{ 55 + ($index + .5) * $step }}" y="{{ 202 - ($row[$key] ?? 0) / $maximum * 172 }}" text-anchor="middle">{{ $row[$key] ?? '—' }}</text>@endif @endforeach
        @endif
    @endforeach
    @foreach($chartRows as $index => $row)<text x="{{ 55 + ($index + .5) * $step }}" y="230" text-anchor="{{ ($showBarValues ?? false) ? 'middle' : 'end' }}" @unless($showBarValues ?? false) transform="rotate(-25 {{ 55 + ($index + .5) * $step }} 230)" @endunless>{{ $row['label'] }}</text>@endforeach
</svg>
@unless($hideChartDetails ?? false)
<details class="analytics-data"><summary>View chart data</summary><div class="analytics-table"><table><thead><tr><th>Period / category</th>@foreach($series as [$key, $caption, $color])<th>{{ $caption }}</th>@endforeach</tr></thead><tbody>@forelse($chartRows as $row)<tr><th scope="row">{{ $row['label'] }}</th>@foreach($series as [$key, $caption, $color])<td>{{ $row[$key] ?? 'Not available' }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($series) + 1 }}">No data available.</td></tr>@endforelse</tbody></table></div></details>
@endunless
