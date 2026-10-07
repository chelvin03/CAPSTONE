@php($equipmentNotifications = auth()->user()->notifications()->where('type', \App\Notifications\EquipmentUpdate::class)->latest()->limit(5)->get())
<details class="relative">
    <summary class="cursor-pointer rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-900">Equipment Updates ({{ $equipmentNotifications->count() }})</summary>
    <div class="absolute right-0 z-50 mt-2 max-h-96 w-72 overflow-y-auto rounded-xl border border-slate-200 bg-white p-4 shadow-lg sm:w-96">
        @forelse($equipmentNotifications as $notice)
            <a href="{{ route('admin.reservations.show', $notice->data['reservation_id']) }}" class="mb-3 block rounded-lg bg-slate-50 p-3 text-sm text-slate-700"><span>{{ $notice->data['message'] }}</span><time class="mt-2 block text-xs text-slate-500">{{ $notice->created_at->format('M d, Y g:i A') }}</time></a>
        @empty
            <p class="text-sm text-slate-500">No equipment responses yet.</p>
        @endforelse
    </div>
</details>
