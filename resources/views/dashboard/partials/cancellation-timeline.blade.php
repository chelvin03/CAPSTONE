<?php
    $cancelRateMaximum = max(50,ceil(max(1,$monthly->max('rate'))/10)*10);
    $cancelStep = 1040 / 12;
?>
<div class="bottom-chart-legend"><span><i class="legend-rate-line"></i>Cancellation Rate</span></div>
<div class="bottom-chart-scroll"><svg class="bottom-chart-svg" viewBox="0 0 1140 235" role="img" aria-label="Monthly cancellation rate percentage">
    @foreach(range(0,5) as $tick)<line x1="55" x2="1095" y1="{{ 180-$tick*30 }}" y2="{{ 180-$tick*30 }}" class="chart-grid"/><text x="43" y="{{ 184-$tick*30 }}" text-anchor="end" class="chart-axis">{{ $cancelRateMaximum*$tick/5 }}%</text>@endforeach
    <line x1="55" x2="55" y1="30" y2="180" class="chart-baseline"/>
    <polyline points="@foreach($monthly as $i => $row){{ 55+($i+.5)*$cancelStep }},{{ 180-$row['rate']/$cancelRateMaximum*150 }} @endforeach" fill="none" stroke="#ef4444" stroke-width="2"/>
    @foreach($monthly as $i => $row)
        <?php $x=55+($i+.5)*$cancelStep; $rateY=180-$row['rate']/$cancelRateMaximum*150; ?>
        <text x="{{ $x }}" y="203" text-anchor="middle" class="chart-axis">{{ $row['label'] }}</text>
        <circle cx="{{ $x }}" cy="{{ $rateY }}" r="3.5" fill="#ef4444"><title>{{ $row['label'] }}: {{ $row['rate'] }}%</title></circle><text x="{{ $x }}" y="{{ $rateY-12 }}" text-anchor="middle" class="cancel-rate-label">{{ $row['rate'] }}%</text>
    @endforeach
    <text x="15" y="110" transform="rotate(-90 15 110)" text-anchor="middle" class="chart-axis">Cancellation Rate (%)</text><text x="575" y="228" text-anchor="middle" class="chart-axis">Month</text>
</svg></div>