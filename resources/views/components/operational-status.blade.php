@props(['status'])
@php
    $colors = [
        'new' => 'bg-blue-50 text-blue-700', 'validated' => 'bg-purple-50 text-purple-700',
        'approved' => 'bg-green-50 text-green-700', 'available' => 'bg-green-50 text-green-700',
        'rejected' => 'bg-red-50 text-red-700', 'waiting_list' => 'bg-orange-50 text-orange-800',
        'cancelled' => 'bg-slate-100 text-slate-700', 'completed' => 'bg-teal-50 text-teal-700',
        'maintenance' => 'bg-amber-50 text-amber-800', 'unavailable' => 'bg-slate-100 text-slate-700',
    ];
@endphp
<span class="status-badge whitespace-nowrap {{ $colors[$status] ?? 'bg-slate-100 text-slate-700' }}">{{ ucwords(str_replace('_', ' ', $status)) }}</span>
