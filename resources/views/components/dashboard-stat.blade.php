@props([
    'title',
    'value',
    'detail',
    'detailClass' => 'text-slate-500',
    'icon',
    'iconClass' => 'bg-slate-50',
])

<article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="flex items-start justify-between">
        <div>
            <p class="text-sm font-medium text-slate-500">{{ $title }}</p>
            <p class="mt-3 text-3xl font-bold text-slate-900">{{ number_format($value) }}</p>
            <p class="mt-2 text-xs font-medium {{ $detailClass }}">{{ $detail }}</p>
        </div>
        <div class="rounded-xl p-3 text-xl {{ $iconClass }}">{!! $icon !!}</div>
    </div>
</article>
