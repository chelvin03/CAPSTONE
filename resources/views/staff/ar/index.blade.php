@extends('layouts.app')
@section('title', 'AR Management')
@section('content')
<link rel="stylesheet" href="{{ asset('css/dashboard-reference.css') }}">
<link rel="stylesheet" href="{{ asset('css/staff-dashboard.css') }}">
<div class="reference-dashboard staff-dashboard">
    <header class="reference-heading"><div><p class="reference-eyebrow">MCST GYMNASIUM</p><h1>AR Management</h1><p>Prepare and complete acknowledgement receipts for approved reservations.</p></div><a class="btn-primary" href="{{ route('staff.dashboard') }}">Staff Dashboard</a></header>
    <form class="reference-date-selectors staff-ar-filters" method="GET">
        <label>AR Status<select name="status"><option value="">All AR statuses</option>@foreach(['pending' => 'Pending', 'in_progress' => 'In Progress', 'completed' => 'Completed'] as $value => $caption)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $caption }}</option>@endforeach</select></label>
        <label><input type="checkbox" name="history" value="1" @checked(request()->boolean('history'))> Include completed and inactive reservation history</label><button class="btn-primary">Apply</button>
    </form>
    <section class="reference-panel">@include('staff.partials.ar-table', ['rows' => $reservations])<div class="staff-pagination">{{ $reservations->links() }}</div></section>
</div>
@endsection
