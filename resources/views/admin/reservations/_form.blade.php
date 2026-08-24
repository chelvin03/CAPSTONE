<div class="rounded-2xl border border-slate-200 bg-white shadow-sm">

    <div class="border-b border-slate-200 px-6 py-5">
        <h2 class="text-lg font-bold text-slate-900">
            Reservation Information
        </h2>

        <p class="mt-1 text-sm text-slate-500">
            Fields marked with an asterisk are required.
        </p>
    </div>

    <div class="grid gap-5 p-6 md:grid-cols-2">

        <div>
            <label for="facility_id" class="mb-2 block text-sm font-semibold text-slate-700">
                Facility <span class="text-red-500">*</span>
            </label>

            <select
                id="facility_id"
                name="facility_id"
                required
                class="w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            >
                <option value="">Select facility</option>

                @foreach ($facilities as $facility)
                    <option
                        value="{{ $facility->id }}"
                        @selected(old('facility_id') == $facility->id)
                    >
                        {{ $facility->facility_name }}
                        — Capacity: {{ $facility->capacity }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="reservation_type" class="mb-2 block text-sm font-semibold text-slate-700">
                Reservation Type <span class="text-red-500">*</span>
            </label>

            <select
                id="reservation_type"
                name="reservation_type"
                required
                class="w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            >
                <option value="">Select type</option>
                <option value="school" @selected(old('reservation_type') === 'school')>
                    School Activity
                </option>
                <option value="community" @selected(old('reservation_type') === 'community')>
                    Community Activity
                </option>
                <option value="sports" @selected(old('reservation_type') === 'sports')>
                    Sports Activity
                </option>
                <option value="government" @selected(old('reservation_type') === 'government')>
                    Government Activity
                </option>
                <option value="other" @selected(old('reservation_type') === 'other')>
                    Other
                </option>
            </select>
        </div>

        <div>
            <label for="event_name" class="mb-2 block text-sm font-semibold text-slate-700">
                Event Name <span class="text-red-500">*</span>
            </label>

            <input
                id="event_name"
                type="text"
                name="event_name"
                value="{{ old('event_name') }}"
                required
                maxlength="255"
                class="w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                placeholder="Example: Basketball Tryout"
            >
        </div>

        <div>
            <label for="event_type" class="mb-2 block text-sm font-semibold text-slate-700">
                Event Type
            </label>

            <input
                id="event_type"
                type="text"
                name="event_type"
                value="{{ old('event_type') }}"
                maxlength="100"
                class="w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                placeholder="Example: Sports competition"
            >
        </div>

        <div class="md:col-span-2">
            <label for="purpose" class="mb-2 block text-sm font-semibold text-slate-700">
                Purpose <span class="text-red-500">*</span>
            </label>

            <textarea
                id="purpose"
                name="purpose"
                rows="4"
                required
                class="w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                placeholder="Describe the purpose of the reservation."
            >{{ old('purpose') }}</textarea>
        </div>

        <div>
            <label for="contact_person" class="mb-2 block text-sm font-semibold text-slate-700">
                Contact Person <span class="text-red-500">*</span>
            </label>

            <input
                id="contact_person"
                type="text"
                name="contact_person"
                value="{{ old('contact_person', auth()->user()->name ?? '') }}"
                required
                maxlength="255"
                class="w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            >
        </div>

        <div>
            <label for="contact_number" class="mb-2 block text-sm font-semibold text-slate-700">
                Contact Number <span class="text-red-500">*</span>
            </label>

            <input
                id="contact_number"
                type="text"
                name="contact_number"
                value="{{ old('contact_number', auth()->user()->contact_number ?? '') }}"
                required
                maxlength="30"
                class="w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                placeholder="09XXXXXXXXX"
            >
        </div>

        <div>
            <label for="expected_attendees" class="mb-2 block text-sm font-semibold text-slate-700">
                Expected Attendees <span class="text-red-500">*</span>
            </label>

            <input
                id="expected_attendees"
                type="number"
                name="expected_attendees"
                value="{{ old('expected_attendees') }}"
                min="1"
                required
                class="w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            >
        </div>

        <div>
            <label for="reservation_date" class="mb-2 block text-sm font-semibold text-slate-700">
                Reservation Date <span class="text-red-500">*</span>
            </label>

            <input
                id="reservation_date"
                type="date"
                name="reservation_date"
                value="{{ old('reservation_date') }}"
                min="{{ now()->format('Y-m-d') }}"
                required
                class="w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            >
        </div>

        <div>
            <label for="start_time" class="mb-2 block text-sm font-semibold text-slate-700">
                Start Time <span class="text-red-500">*</span>
            </label>

            <input
                id="start_time"
                type="time"
                name="start_time"
                value="{{ old('start_time') }}"
                required
                class="w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            >
        </div>

        <div>
            <label for="end_time" class="mb-2 block text-sm font-semibold text-slate-700">
                End Time <span class="text-red-500">*</span>
            </label>

            <input
                id="end_time"
                type="time"
                name="end_time"
                value="{{ old('end_time') }}"
                required
                class="w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            >
        </div>

        <div>
            <label for="setup_time" class="mb-2 block text-sm font-semibold text-slate-700">
                Setup Time
            </label>

            <input
                id="setup_time"
                type="time"
                name="setup_time"
                value="{{ old('setup_time') }}"
                class="w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            >
        </div>

        <div>
            <label for="cleanup_time" class="mb-2 block text-sm font-semibold text-slate-700">
                Cleanup Time
            </label>

            <input
                id="cleanup_time"
                type="time"
                name="cleanup_time"
                value="{{ old('cleanup_time') }}"
                class="w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            >
        </div>

    </div>
</div>

<div class="rounded-2xl border border-slate-200 bg-white shadow-sm">

    <div class="border-b border-slate-200 px-6 py-5">
        <h2 class="text-lg font-bold text-slate-900">
            Equipment Request
        </h2>

        <p class="mt-1 text-sm text-slate-500">
            Leave the quantity blank when the equipment is not needed.
        </p>
    </div>

    <div class="p-6">

        @if ($equipment->isEmpty())
            <div class="rounded-lg bg-slate-50 p-4 text-sm text-slate-500">
                No available equipment found.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">
                                Equipment
                            </th>

                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">
                                Available
                            </th>

                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">
                                Requested Quantity
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-200 bg-white">
                        @foreach ($equipment as $item)
                            <tr>
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-slate-800">
                                        {{ $item->equipment_name }}
                                    </p>

                                    <p class="text-xs text-slate-500">
                                        {{ $item->description ?: 'No description' }}
                                    </p>
                                </td>

                                <td class="px-4 py-4 text-sm text-slate-700">
                                    {{ $item->total_quantity }}
                                    {{ $item->unit }}
                                </td>

                                <td class="px-4 py-4">
                                    <input
                                        type="number"
                                        name="equipment[{{ $item->id }}][quantity_requested]"
                                        value="{{ old("equipment.{$item->id}.quantity_requested") }}"
                                        min="1"
                                        max="{{ $item->total_quantity }}"
                                        class="w-40 rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        placeholder="0"
                                    >
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

    </div>
</div>
