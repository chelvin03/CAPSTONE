@php($chartMaximum = max(1, collect($rows)->max('count')))
<div class="mt-5 overflow-x-auto" tabindex="0" role="region" aria-label="{{ $chartLabel }}">
    <div style="display: flex; align-items: flex-end; gap: 12px; min-width: {{ max(600, count($rows) * 65) }}px; height: 190px; border-bottom: 1px solid #cbd5e1; padding-bottom: 8px;">
        @foreach($rows as $row)
            <div style="flex: 1; text-align: center; font-size: 12px;" aria-label="{{ $row['label'] }}: {{ $row['count'] }} reservations">
                <strong>{{ number_format($row['count']) }}</strong>
                <div style="height: {{ $row['count'] / $chartMaximum * 130 }}px; background: #2563eb; max-width: 36px; margin: 6px auto; border-radius: 5px 5px 0 0;"></div>
                <span>{{ $row['label'] }}</span>
            </div>
        @endforeach
    </div>
</div>
