@php($donutRows = ($showEmptySegments ?? false) ? collect($rows)->values() : collect($rows)->where('count', '>', 0)->values())
@php($donutTotal = $donutRows->sum('count'))
@php($donutOffset = 0)
@php($palette = ($showEmptySegments ?? false) ? ['#12529a', '#287cf0', '#ffbd44', '#ef5363', '#7856d8', '#94a3b8'] : ['#12529a', '#287cf0', '#ffbd44', '#31b47a', '#7856d8', '#94a3b8', '#dc5965', '#0d9488', '#d97706'])
<div class="analytics-donut-layout"><div class="analytics-donut"><svg viewBox="0 0 120 120" role="img" aria-label="{{ $chartTitle }}: {{ $donutTotal }} total"><circle cx="60" cy="60" r="44" fill="none" stroke="#edf2f8" stroke-width="19"/>
@foreach($donutRows as $index => $row)
    @php($share = $donutTotal ? $row['count'] / $donutTotal * 100 : 0)
    <circle cx="60" cy="60" r="44" fill="none" stroke="{{ $palette[$index % count($palette)] }}" stroke-width="19" pathLength="100" stroke-dasharray="{{ $share }} {{ 100 - $share }}" stroke-dashoffset="{{ -$donutOffset }}" transform="rotate(-90 60 60)"><title>{{ $row['label'] }}: {{ $row['count'] }} ({{ round($share, 1) }}%)</title></circle>
    @if(($showEmptySegments ?? false) && $share >= 4)
        @php($angle = deg2rad(($donutOffset + $share / 2) * 3.6 - 90))
        <text x="{{ 60 + cos($angle) * 44 }}" y="{{ 60 + sin($angle) * 44 }}" fill="white" text-anchor="middle" dominant-baseline="middle" font-size="7">{{ round($share) }}%</text>
    @endif
    @php($donutOffset += $share)
@endforeach</svg><div><strong>{{ $donutTotal }}</strong><span>{{ $donutCaption ?? 'Total requests' }}</span></div></div>
<ul>@forelse($donutRows as $index => $row)<li><i style="background:{{ $palette[$index % count($palette)] }}"></i><span>{{ $row['label'] }}</span><b>{{ $row['count'] }} ({{ $donutTotal ? round($row['count'] / $donutTotal * 100, 1) : 0 }}%)</b></li>@empty<li>No data available.</li>@endforelse</ul></div>
