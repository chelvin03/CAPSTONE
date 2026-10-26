@extends('layouts.app')
@section('title', 'Analytics')
@section('content')
<link rel="stylesheet" href="{{ asset('css/analytics.css') }}?v={{ filemtime(public_path('css/analytics.css')) }}">
@php
    $pages = ['reservations' => ['Reservation Analytics', 'Monitor gymnasium booking demand, trends, and user groups.'], 'operations' => ['Operations & Service Analytics', 'Monitor processing efficiency, reservation outcomes, and service performance.'], 'utilization' => ['Facility Utilization Analytics', 'Track booked hours, utilization patterns, and facility usage.']];
    $label = fn ($value) => \App\Services\DashboardAnalyticsService::label($value);
    $format = fn ($value, $suffix = '') => $value === null ? 'Not available' : number_format((float) $value, is_float($value) ? 1 : 0).$suffix;
    $rejected = $statusBreakdown->firstWhere('status', 'rejected')['count'] ?? 0;
    $approvalRate = $performance['approvalRate'];
    $cards = match($section) {
        'operations' => [
            ['Average Approval Time', $format($approvalDays, ' days'), 'bi-clock', $approvalSample.' valid approval timestamps'],
            ['Pending Requests', $performance['pending']->sum(fn ($rows) => $rows->count()), 'bi-file-earmark-text', 'New, validated, and legacy pending'],
            ['Rejection Rate', $format($performance['rejectionRate'], '%'), 'bi-x-circle', 'Rejected / total requests'],
            ['Cancellation Rate', $format($cancellationRate, '%'), 'bi-arrow-repeat', 'Cancelled / total requests'],
            ['Equipment Fulfillment Rate', $format($equipmentFulfillment, '%'), 'bi-tools', 'Approved units / requested units'],
            ['Average Satisfaction', $format($feedbackAverage, ' / 5'), 'bi-star', $feedbackCount.' feedback responses'],
        ],
        'utilization' => [
            ['Available Facility Hours', $format($availableHours), 'bi-calendar3', 'Operating hours less blocked time'],
            ['Approved Booked Hours', $format($utilizedHours), 'bi-check-circle', 'Overlaps merged; operating hours only'],
            ['Scheduled Utilization', $format($utilizationRate, '%'), 'bi-percent', 'Booked / available facility hours'],
            ['Actual Usage', 'Not available', 'bi-clock-history', 'Actual start/end times are not recorded'],
            ['Unused Booked Hours', $format($unusedHours), 'bi-x-circle', 'Verified no-show scheduled hours only'],
            ['Peak Usage Period', $peakTime, 'bi-bar-chart', 'Most approved bookings; ties use earliest slot'],
        ],
        default => [
            ['Total Requests', $totalReservations, 'bi-file-earmark-text', 'All matching reservation requests'],
            ['Approved Bookings', $approvedReservations, 'bi-check-circle', 'Currently approved reservations'],
            ['Approval Rate', $format($approvalRate, '%'), 'bi-percent', 'Ever approved / total matching requests'],
            ['Cancelled Bookings', $cancelledReservations, 'bi-x-circle', 'Currently cancelled reservations'],
            ['Most Requested Day', $peakWeekday['label'], 'bi-calendar3', $peakWeekday['count'].' requests'],
            ['Most Active User Group', $requestorDemand->first()['label'] ?? 'Not available', 'bi-people', ($requestorDemand->first()['count'] ?? 0).' requests'],
        ],
    };
@endphp
<div class="analytics-workspace {{ $section === 'utilization' ? 'facility-utilization' : '' }}">
    <div class="analytics-heading"><div>@if($section !== 'utilization')<p class="analytics-eyebrow">MCST GYMNASIUM</p>@endif<h1>{{ $pages[$section][0] }}</h1><p>{{ $pages[$section][1] }}</p></div>
        <form method="GET" action="{{ route('admin.analytics', ['section' => $section]) }}" class="analytics-filters">
            <label>From<input type="date" name="date_from" value="{{ $filters['date_from'] }}" required></label>
            <label>To<input type="date" name="date_to" value="{{ $filters['date_to'] }}" required></label>
            @if($section !== 'utilization')<label>Facility<select name="facility_id"><option value="">All facilities</option>@foreach($facilities as $facility)<option value="{{ $facility->id }}" @selected(($filters['facility_id'] ?? '') == $facility->id)>{{ $facility->facility_name }}</option>@endforeach</select></label>@elseif(isset($filters['facility_id']))<input type="hidden" name="facility_id" value="{{ $filters['facility_id'] }}">@endif
            <button type="submit">Apply</button>
        </form>
    </div>
    @if($errors->any())<div role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <nav class="analytics-tabs" aria-label="Analytics views">@foreach($pages as $key => [$title, $description])<a href="{{ route('admin.analytics', ['section' => $key] + \Illuminate\Support\Arr::only($filters, ['date_from', 'date_to', 'facility_id'])) }}" @if($key === $section) aria-current="page" @endif>{{ $title }}</a>@endforeach</nav>
    @if($section !== 'utilization')
    <p class="analytics-scope">{{ \Carbon\CarbonImmutable::parse($filters['date_from'])->format('M j, Y') }} – {{ \Carbon\CarbonImmutable::parse($filters['date_to'])->format('M j, Y') }} · Metrics follow scheduled event dates.</p>
    <div class="analytics-cards">@foreach($cards as [$title, $value, $icon, $note])<article class="analytics-stat"><i class="bi {{ $icon }}" aria-hidden="true"></i><div><h2>{{ $title }}</h2><strong>{{ $value }}</strong><p>{{ $note }}</p></div></article>@endforeach</div>
    @else
        @include('admin.analytics.utilization-cards')
    @endif
    @if(!$totalReservations)<p class="analytics-empty" role="status">No reservations match this period and facility. Choose a different range to explore your data.</p>@endif
    @include('admin.analytics.'.$section)
</div>
@endsection
