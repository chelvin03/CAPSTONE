<section class="detail-documents detail-card" x-data="{ openDocument: null }">
    <header class="detail-card-heading"><h2><i class="bi bi-paperclip" aria-hidden="true"></i> Attached Approval Letter / Supporting Documents</h2></header>
    @forelse($reservation->documents as $document)
    <div class="detail-document-file">
        <i class="bi bi-file-earmark-pdf-fill" aria-hidden="true"></i>
        <div><p>{{ $document->original_filename }}</p><small>{{ number_format($document->file_size / 1024, 0) }} KB</small></div>
        <button type="button" class="detail-small-button" @click="openDocument = {{ $document->id }}; $nextTick(() => document.getElementById('document-preview-{{ $document->id }}').showModal())" aria-haspopup="dialog" aria-controls="document-preview-{{ $document->id }}"><i class="bi bi-eye" aria-hidden="true"></i> View</button>
    </div>
    <dialog id="document-preview-{{ $document->id }}" class="detail-document-modal" aria-labelledby="document-title-{{ $document->id }}" @close="openDocument = null" @click="if ($event.target === $el) $el.close()">
        <div class="detail-document-modal-content">
            <header class="detail-document-modal-header">
                <div><p id="document-title-{{ $document->id }}">{{ $document->original_filename }}</p><a href="{{ route('admin.reservations.documents.preview', [$reservation, $document]) }}" target="_blank" rel="noopener">Open document in a new tab <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a></div>
                <button type="button" class="detail-small-button" autofocus @click="$el.closest('dialog').close()" aria-label="Close document preview"><i class="bi bi-x-lg" aria-hidden="true"></i> Close</button>
            </header>
            <template x-if="openDocument === {{ $document->id }}"><iframe src="{{ route('admin.reservations.documents.preview', [$reservation, $document]) }}" title="Preview {{ $document->original_filename }}" class="detail-document-modal-frame"></iframe></template>
        </div>
    </dialog>
    @empty
    <p class="detail-empty-documents">No supporting documents attached.</p>
    @endforelse
</section>
