<div class="overflow-x-auto" tabindex="0" role="region" aria-label="{{ $caption }}">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        <caption class="sr-only">{{ $caption }}</caption>
        <thead class="bg-slate-50"><tr>
            @foreach (['Reference Number', 'Event Name', 'Event Type', 'Scheduled Date', 'Start–End Time', 'Expected Attendees', 'Facility', 'Status', 'View'] as $heading)
                <th scope="col" class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold text-slate-600">{{ $heading }}</th>
            @endforeach
        </tr></thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($rows as $row)
                <tr class="hover:bg-slate-50">
                    <td class="whitespace-nowrap px-4 py-4 font-medium">{{ $row->reference_number }}</td>
                    <td class="min-w-40 px-4 py-4">{{ $row->event_name }}</td>
                    <td class="px-4 py-4">{{ $row->event_type ?: 'Not specified' }}</td>
                    <td class="whitespace-nowrap px-4 py-4">{{ $row->reservation_date->format('M d, Y') }}</td>
                    <td class="whitespace-nowrap px-4 py-4">{{ substr($row->start_time, 0, 5) }}–{{ substr($row->end_time, 0, 5) }}</td>
                    <td class="px-4 py-4">{{ number_format($row->expected_attendees) }}</td>
                    <td class="px-4 py-4">{{ $row->facility?->facility_name ?? 'Not assigned' }}</td>
                    <td class="px-4 py-4"><x-operational-status :status="$row->status" /></td>
                    <td class="px-4 py-4"><a class="font-semibold text-blue-700 underline" href="{{ route('staff.reservations.show', $row) }}" aria-label="View {{ $row->reference_number }}">View</a></td>
                </tr>
            @empty
                <tr><td colspan="9" class="px-5 py-10 text-center text-slate-500">{{ $emptyMessage }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
