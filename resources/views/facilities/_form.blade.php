@csrf

<div>
    <label
        for="facility_name"
        class="mb-2 block text-sm font-semibold text-slate-700"
    >
        Facility Name
    </label>

    <input
        id="facility_name"
        type="text"
        name="facility_name"
        value="{{ old('facility_name', $facility->facility_name ?? '') }}"
        required
        class="block w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
    >

    @error('facility_name')
        <p class="mt-1 text-xs font-medium text-red-600">
            {{ $message }}
        </p>
    @enderror
</div>

<div class="mt-4">
    <label
        for="description"
        class="mb-2 block text-sm font-semibold text-slate-700"
    >
        Description
    </label>

    <textarea
        id="description"
        name="description"
        rows="4"
        class="block w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
    >{{ old('description', $facility->description ?? '') }}</textarea>

    @error('description')
        <p class="mt-1 text-xs font-medium text-red-600">
            {{ $message }}
        </p>
    @enderror
</div>

<div class="mt-4 grid gap-4 sm:grid-cols-2">
    <div>
        <label
            for="location"
            class="mb-2 block text-sm font-semibold text-slate-700"
        >
            Location
        </label>

        <input
            id="location"
            type="text"
            name="location"
            value="{{ old('location', $facility->location ?? '') }}"
            class="block w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
        >

        @error('location')
            <p class="mt-1 text-xs font-medium text-red-600">
                {{ $message }}
            </p>
        @enderror
    </div>

    <div>
        <label
            for="capacity"
            class="mb-2 block text-sm font-semibold text-slate-700"
        >
            Capacity
        </label>

        <input
            id="capacity"
            type="number"
            name="capacity"
            min="1"
            value="{{ old('capacity', $facility->capacity ?? '') }}"
            required
            class="block w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
        >

        @error('capacity')
            <p class="mt-1 text-xs font-medium text-red-600">
                {{ $message }}
            </p>
        @enderror
    </div>
</div>

<div class="mt-4">
    <label
        for="status"
        class="mb-2 block text-sm font-semibold text-slate-700"
    >
        Status
    </label>

    <select
        id="status"
        name="status"
        required
        class="block w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
    >
        <option
            value="available"
            @selected(old('status', $facility->status ?? 'available') === 'available')
        >
            Available
        </option>

        <option
            value="unavailable"
            @selected(old('status', $facility->status ?? '') === 'unavailable')
        >
            Unavailable
        </option>

        <option
            value="maintenance"
            @selected(old('status', $facility->status ?? '') === 'maintenance')
        >
            Maintenance
        </option>
    </select>

    @error('status')
        <p class="mt-1 text-xs font-medium text-red-600">
            {{ $message }}
        </p>
    @enderror
</div>
