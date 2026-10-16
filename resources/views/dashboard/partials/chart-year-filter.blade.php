<form method="GET" action="{{ route('admin.dashboard') }}" class="chart-year-filter">
    @foreach(\Illuminate\Support\Arr::only($filters, ['category','status','requestor_type','heat_scope','month','facility_id']) as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
    <label class="sr-only" for="{{ $yearFilterId }}">{{ $yearFilterLabel }}</label><select id="{{ $yearFilterId }}" name="year" onchange="this.form.requestSubmit()"><option value="all" @selected($selectedYear === 'all')>All years</option>@foreach($years as $year)<option value="{{ $year }}" @selected($selectedYear === (string)$year)>{{ $year }}</option>@endforeach</select><noscript><button type="submit">Apply</button></noscript>
</form>
