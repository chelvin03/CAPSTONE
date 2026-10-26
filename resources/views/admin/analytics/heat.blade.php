@unless($hideHeatNote ?? false)<p class="analytics-note">Approved reservation counts by overlapping time slot. This shows scheduled demand, not measured attendance.</p>@endunless
<div class="analytics-table analytics-heat-scroll"><table class="analytics-heat" aria-label="Approved bookings by weekday and time slot">
    <colgroup><col style="width:36px">@foreach($heat as $slot)<col>@endforeach</colgroup>
    <thead class="sr-only"><tr><th scope="col">Day</th>@foreach($heat as $slot)<th scope="col">{{ $slot['label'] }}</th>@endforeach</tr></thead>
    <tbody>@foreach($weekdays as $dayIndex => $day)<tr><th scope="row">{{ substr($day, 0, 3) }}</th>@foreach($heat as $slot)<td style="background:rgba(19,107,231,{{ .06 + .94 * $slot['counts'][$dayIndex] / $heatMax }});color:{{ $slot['counts'][$dayIndex] / $heatMax > .5 ? '#fff' : '#18365b' }}" title="{{ $day }} {{ $slot['label'] }}: {{ round($slot['counts'][$dayIndex], 2) }} {{ ($heatMetric ?? '') === 'hours' ? 'booked hours' : 'approved bookings' }}"><span @class(['sr-only' => ($heatMetric ?? '') === 'hours'])>{{ round($slot['counts'][$dayIndex], 2) }}</span></td>@endforeach</tr>@endforeach</tbody>
    <tfoot aria-hidden="true"><tr><th></th>@foreach($heat as $slot)
        @php
            $slotStart = \Carbon\CarbonImmutable::today()->addMinutes($slot['start']);
            $slotEnd = \Carbon\CarbonImmutable::today()->addMinutes($slot['end']);
            $sameMeridiem = $slotStart->format('A') === $slotEnd->format('A');
            $startClock = $slotStart->format($slotStart->minute ? 'g:i' : 'g');
            $endClock = $slotEnd->format($slotEnd->minute ? 'g:i' : 'g');
        @endphp
        <td><span>{{ $sameMeridiem ? $startClock.'–'.$endClock : $startClock.' '.$slotStart->format('A') }}</span><span>{{ $sameMeridiem ? $slotEnd->format('A') : '–'.$endClock.' '.$slotEnd->format('A') }}</span></td>
    @endforeach</tr></tfoot>
</table></div>
<div class="analytics-heat-key"><span>{{ ($heatMetric ?? '') === 'hours' ? 'Low Usage' : 'Low demand' }}</span><i></i><span>{{ ($heatMetric ?? '') === 'hours' ? 'High Usage' : 'High demand' }}</span></div>
