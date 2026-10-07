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
                x-model="facility"
                @change="loadAvailability"
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
                name="reservation_type" x-model="requestorType"
                required
                class="w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            >
                <option value="">Select type</option>
                <option value="internal">Internal</option><option value="external">External</option>
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
                x-model="contactPerson"
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
                x-model="contactNumber"
                value="{{ old('contact_number', auth()->user()->contact_number ?? '') }}"
                required
                maxlength="30"
                class="w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                placeholder="09XXXXXXXXX"
            >
        </div>

        <div>
            <label for="contact_email" class="mb-2 block text-sm font-semibold text-slate-700">Contact Email</label>
            <input id="contact_email" type="email" name="contact_email" x-model="email" value="{{ old('contact_email') }}" maxlength="255" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="requestor@example.com">
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
             max="2000" step="1">
        </div>

                <div>
                    <h2 class="mb-4 text-xs font-bold uppercase text-blue-700">1. Event Schedule</h2>
                    <div class="grid gap-5 md:grid-cols-2">
                        <div><label class="mb-2 block text-sm font-semibold">Event Title *</label><input name="event_name" value="{{ old('event_name') }}" required class="w-full rounded-lg border-slate-300"></div>
                        <div><label class="mb-2 block text-sm font-semibold">Event Type</label><input name="event_type" value="{{ old('event_type') }}" class="w-full rounded-lg border-slate-300" placeholder="Sports Event"></div>
                        <div><label class="mb-2 block text-sm font-semibold">Facility *</label><select name="facility_id" x-model="facility" @change="loadAvailability" required class="w-full rounded-lg border-slate-300"><option value="">Select facility</option>@foreach($facilities as $facility)<option value="{{ $facility->id }}">{{ $facility->facility_name }}</option>@endforeach</select></div>
                        <div><label class="mb-2 block text-sm font-semibold">Estimated Participants *</label><input name="expected_attendees" type="number" min="1" value="{{ old('expected_attendees') }}" required class="w-full rounded-lg border-slate-300" max="2000" step="1"></div>
                        <div class="md:col-span-2"><label class="mb-2 block text-sm font-semibold">Purpose *</label><textarea name="purpose" rows="3" required class="w-full rounded-lg border-slate-300">{{ old('purpose') }}</textarea></div>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200">
                    <div class="border-b border-slate-200 bg-slate-50 p-4">
                        <label id="reservation_date_label" class="mb-3 block text-sm font-semibold">Reservation Date *</label>
                        <input id="reservation_date" name="reservation_date" type="hidden" x-model="date">
                        <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm sm:p-5" role="group" aria-labelledby="reservation_date_label">
                            <div class="mb-5 grid grid-cols-[2.5rem_1fr_2.5rem] items-center">
                                <button type="button" @click="changeMonth(-1)" :disabled="!canMoveMonth(-1)" class="rounded-lg p-2 text-xl font-bold text-blue-600 hover:bg-blue-50 disabled:cursor-not-allowed disabled:text-slate-300" aria-label="Previous month">&lsaquo;</button>
                                <h3 class="text-center text-lg font-bold text-slate-900" x-text="calendarMonthLabel"></h3>
                                <button type="button" @click="changeMonth(1)" :disabled="!canMoveMonth(1)" class="rounded-lg p-2 text-xl font-bold text-blue-600 hover:bg-blue-50 disabled:cursor-not-allowed disabled:text-slate-300" aria-label="Next month">&rsaquo;</button>
                            </div>
                            <div class="mb-2 grid grid-cols-7 gap-1 text-center text-[0.65rem] font-bold uppercase text-slate-500 sm:gap-2 sm:text-xs" style="grid-template-columns: repeat(7, minmax(0, 1fr));">
                                <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
                            </div>
                            <div class="grid grid-cols-7 gap-1 sm:gap-2" style="grid-template-columns: repeat(7, minmax(0, 1fr));">
                                <template x-for="(day, index) in calendarDays" :key="index">
                                    <div>
                                        <span x-show="!day" class="block h-10 sm:h-11"></span>
                                        <button x-show="day" type="button" @click="selectDate(day.date)" :disabled="!day.selectable" :class="date === day.date ? 'border-blue-600 bg-blue-600 font-bold text-white shadow-sm' : (day.selectable ? 'border-slate-200 bg-white text-slate-700 hover:border-blue-400 hover:bg-blue-50' : 'cursor-not-allowed border-slate-100 bg-slate-50 text-slate-300')" class="h-10 w-full rounded-md border text-sm transition sm:h-11" :aria-label="day.label" :aria-pressed="date === day.date" x-text="day.number"></button>
                                    </div>
                                </template>
                            </div>
                            <div class="mt-4 flex flex-wrap items-center justify-between gap-2 text-xs">
                                <p class="text-slate-500">Earliest available date: {{ now()->addDays($minimumNoticeDays)->format('F j, Y') }} ({{ $minimumNoticeDays }} days' notice)</p>
                                <p x-show="date" class="font-semibold text-blue-700">Selected: <span x-text="selectedDateLabel"></span></p>
                            </div>
                        </div>
                    </div>
                    <div class="grid gap-4 border-b border-slate-200 bg-slate-50 p-4 md:grid-cols-2">
                        <div><label class="mb-2 block text-sm font-semibold">Start Time *</label><input name="start_time" type="time" x-model="startTime" required class="w-full rounded-lg border-slate-300"></div>
                        <div><label class="mb-2 block text-sm font-semibold">End Time *</label><input name="end_time" type="time" x-model="endTime" required class="w-full rounded-lg border-slate-300"><p x-show="totalHours" class="mt-1 text-xs text-slate-500">Total: <span x-text="totalHours"></span> hours</p></div>
                    </div>
                    <div class="p-4">
                        <div class="mb-3 flex items-center gap-4 text-xs"><span><i class="mr-1 inline-block h-2.5 w-2.5 rounded-full bg-emerald-500"></i>Available</span><span><i class="mr-1 inline-block h-2.5 w-2.5 rounded-full bg-rose-500"></i>Occupied</span></div>
                        <p x-show="availabilityLoading" class="py-6 text-center text-sm text-blue-700" role="status">Checking availability…</p>
                        <p x-show="availabilityError" x-text="availabilityError" class="py-6 text-center text-sm text-red-700" role="alert"></p>
                        <p x-show="!availabilityLoading && !date" class="py-6 text-center text-sm text-slate-500">Select a date to view occupied hours.</p>
                        <p x-show="!availabilityLoading && !availabilityError && date && occupied.length === 0" class="py-6 text-center text-sm text-emerald-700">No existing reservations for this date.</p>
                        <div x-show="occupied.length" class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-slate-100"><tr><th class="p-2 text-left">Occupied Time</th><th class="p-2 text-left">Status</th></tr></thead><tbody><template x-for="slot in occupied"><tr class="border-t"><td class="p-2" x-text="formatSlot(slot)"></td><td class="p-2 capitalize" x-text="slot.status.replace('_', ' ')"></td></tr></template></tbody></table></div>
                    </div>
                </div>

        <div>
            <label for="start_time" class="mb-2 block text-sm font-semibold text-slate-700">
                Start Time <span class="text-red-500">*</span>
            </label>

            <input
                id="start_time"
                type="time"
                name="start_time"
                x-model="startTime"
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
                x-model="endTime"
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

        <label class="md:col-span-2 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <input type="checkbox" name="priority_override" value="1" @checked(old('priority_override')) class="mt-1 rounded border-amber-300">
            <span><strong>Authorized priority reservation override.</strong> If this schedule is occupied or pending, move affected reservations to the waiting list and notify their requestors.</span>
        </label>

    </div>
</div>

<fieldset x-show="requestorType === 'internal'" :disabled="requestorType !== 'internal'"><div class="rounded-2xl border border-slate-200 bg-white shadow-sm">

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
            <div class="space-y-4">
                <div class="grid gap-3 sm:grid-cols-[1fr_14rem_5rem]">
                    <div>
                        <label for="equipment_select" class="mb-2 block text-sm font-semibold text-slate-700">Choose equipment</label>
                        <select id="equipment_select" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Select equipment</option>
                            @foreach($equipment as $item)
                                <option value="{{ $item->id }}" data-available="{{ $item->total_quantity }}" data-unit="{{ $item->unit }}">{{ $item->equipment_name }} — {{ $item->total_quantity }} {{ $item->unit }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="equipment_quantity" class="mb-2 block text-sm font-semibold text-slate-700">Quantity</label>
                        <input id="equipment_quantity" type="number" min="1" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="0">
                    </div>

                    <div class="flex items-end">
                        <button id="add_equipment_btn" type="button" class="w-full rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">Add</button>
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700">Selected Equipment</label>
                    <div id="selected_equipment_container" class="rounded-lg border border-slate-100 bg-white">
                        <table id="selected_equipment_table" class="min-w-full text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Equipment</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Available</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Requested</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white" id="selected_equipment_rows">
                                {{-- JS will populate selected rows here --}}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

    </div>
</div>

</fieldset><script>
    (function () {
        const equipment = [
            @foreach($equipment as $it)
                { id: {{ $it->id }}, name: @json($it->equipment_name), available: {{ $it->total_quantity }}, unit: @json($it->unit) }@if (! $loop->last),@endif
            @endforeach
        ];
        const select = document.getElementById('equipment_select');
        const qtyInput = document.getElementById('equipment_quantity');
        const addBtn = document.getElementById('add_equipment_btn');
        const rowsContainer = document.getElementById('selected_equipment_rows');

        const selected = {};

        function render() {
            rowsContainer.innerHTML = '';
            Object.keys(selected).forEach(id => {
                const item = selected[id];
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="px-4 py-4">
                        <p class="font-semibold text-slate-800">${item.name}</p>
                    </td>
                    <td class="px-4 py-4 text-sm text-slate-700">${item.available} ${item.unit}</td>
                    <td class="px-4 py-4">
                        <input type="number" name="equipment[${item.id}][quantity_requested]" value="${item.requested}" min="1" max="${item.available}" class="w-40 rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </td>
                    <td class="px-4 py-4 text-right"><button type="button" data-remove="${item.id}" class="text-sm text-rose-600 hover:underline">Remove</button></td>
                `;

                rowsContainer.appendChild(tr);
            });

            // attach remove handlers
            rowsContainer.querySelectorAll('button[data-remove]').forEach(btn => {
                btn.addEventListener('click', function () {
                    const id = this.getAttribute('data-remove');
                    delete selected[id];
                    render();
                });
            });
        }

        addBtn.addEventListener('click', function () {
            const id = select.value;
            const qty = parseInt(qtyInput.value, 10) || 0;

            if (!id) return alert('Select equipment to add.');
            const meta = equipment.find(e => String(e.id) === String(id));
            if (!meta) return alert('Invalid equipment selected.');
            if (qty < 1) return alert('Enter a requested quantity of at least 1.');
            if (qty > meta.available) return alert('Requested quantity exceeds available stock.');

            selected[id] = { id: meta.id, name: meta.name, available: meta.available, unit: meta.unit, requested: qty };
            // reset inputs
            select.value = '';
            qtyInput.value = '';
            render();
        });

        // Prefill from old input if present
        (function prefill() {
            try {
                const old = @json(old('equipment', []));
                Object.keys(old).forEach(id => {
                    const qty = old[id]?.quantity_requested || 0;
                    if (qty > 0) {
                        const meta = equipment.find(e => String(e.id) === String(id));
                        if (meta) selected[id] = { id: meta.id, name: meta.name, available: meta.available, unit: meta.unit, requested: qty };
                    }
                });
                render();
            } catch (e) {
                // ignore
            }
        })();
    })();
</script>
