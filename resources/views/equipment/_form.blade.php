@csrf

<div>
    <label
        for="equipment_name"
        class="mb-2 block text-sm font-semibold text-slate-700"
    >
        Equipment Name
    </label>

    <input
        id="equipment_name"
        type="text"
        name="equipment_name"
        value="{{ old('equipment_name', $equipment->equipment_name ?? '') }}"
        required
        class="block w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
    >

    @error('equipment_name')
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
    >{{ old('description', $equipment->description ?? '') }}</textarea>

    @error('description')
        <p class="mt-1 text-xs font-medium text-red-600">
            {{ $message }}
        </p>
    @enderror
</div>

<div class="mt-4 grid gap-4 sm:grid-cols-2">
    <div>
        <label
            for="total_quantity"
            class="mb-2 block text-sm font-semibold text-slate-700"
        >
            Total Quantity
        </label>

        <input
            id="total_quantity"
            type="number"
            name="total_quantity"
            min="0"
            value="{{ old('total_quantity', $equipment->total_quantity ?? '') }}"
            required
            class="block w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
        >

        @error('total_quantity')
            <p class="mt-1 text-xs font-medium text-red-600">
                {{ $message }}
            </p>
        @enderror
    </div>

    <div>
        <label
            for="unit"
            class="mb-2 block text-sm font-semibold text-slate-700"
        >
            Unit
        </label>

        <input
            id="unit"
            type="text"
            name="unit"
            value="{{ old('unit', $equipment->unit ?? 'piece') }}"
            required
            class="block w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
        >

        @error('unit')
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
            @selected(old('status', $equipment->status ?? 'available') === 'available')
        >
            Available
        </option>

        <option
            value="unavailable"
            @selected(old('status', $equipment->status ?? '') === 'unavailable')
        >
            Unavailable
        </option>

        <option
            value="maintenance"
            @selected(old('status', $equipment->status ?? '') === 'maintenance')
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
