<form method="GET" action="{{ route('admin.analytics', ['section' => 'utilization']) }}" class="facility-control">
    @foreach(\Illuminate\Support\Arr::only($filters, ['date_from', 'date_to', 'facility_id']) + $facilityWorkspace['options'] as $name => $value)
        @if($name !== $controlName)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endif
    @endforeach
    <select name="{{ $controlName }}" aria-label="{{ $controlLabel }}" onchange="this.form.requestSubmit()">@foreach($choices as $value => $caption)<option value="{{ $value }}" @selected(($facilityWorkspace['options'][$controlName] ?? array_key_first($choices)) === $value)>{{ $caption }}</option>@endforeach</select><noscript><button type="submit">Apply</button></noscript>
</form>
