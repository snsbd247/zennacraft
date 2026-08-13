<div class="zc-od-notelist" data-region="order-notes">
    @forelse ($order->orderNotes as $note)
        <div class="zc-od-note">
            <div class="zc-od-note__body">{{ $note->note }}</div>
            <div class="zc-od-note__meta">{{ $note->staffUser?->name ?? 'Staff' }} · {{ $note->created_at?->diffForHumans() }}</div>
        </div>
    @empty
        <div class="zc-od-muted">No notes yet.</div>
    @endforelse
</div>
