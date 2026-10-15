<section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" x-data="{ openDocument: null }">
    <h2 class="text-lg font-bold">Attached Approval Letter / Supporting Documents</h2>
    <p class="mt-2 text-sm text-slate-500">Click the eye to view a letter. Click it again to close the preview.</p>
    <div class="mt-4 space-y-4">
        @forelse($reservation->documents as $document)
            <div class="rounded-xl border border-slate-200 p-4">
                <div class="flex flex-wrap items-center justify-between gap-3"><span class="break-all font-medium">{{ $document->original_filename }}</span><button type="button" class="btn-secondary" @click="openDocument = openDocument === {{ $document->id }} ? null : {{ $document->id }}" :aria-expanded="openDocument === {{ $document->id }}" aria-controls="document-preview-{{ $document->id }}"><i class="bi bi-eye" aria-hidden="true"></i> View Letter</button></div>
                <div id="document-preview-{{ $document->id }}" x-show="openDocument === {{ $document->id }}" x-cloak class="mt-4">
                    <a class="mb-3 inline-block text-blue-700 underline" href="{{ route('admin.reservations.documents.preview', [$reservation, $document]) }}" target="_blank" rel="noopener">Open letter in a new tab</a>
                    <template x-if="openDocument === {{ $document->id }}"><iframe src="{{ route('admin.reservations.documents.preview', [$reservation, $document]) }}" title="Preview {{ $document->original_filename }}" style="width:100%;height:600px;max-height:70vh;border:1px solid #e2e8f0;border-radius:8px;"></iframe></template>
                </div>
            </div>
        @empty
            <p class="text-sm text-slate-500">No letter or supporting document was uploaded for this reservation.</p>
        @endforelse
    </div>
</section>
