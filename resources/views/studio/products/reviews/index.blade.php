@extends('layouts.studio')
@section('title', 'Products Review')
@section('subtitle', 'Products')
@push('studio-styles')@include('studio.products.partials._submodule-styles')@endpush
@section('content')
<div class="space-y-4">
    <div class="studio-card" style="padding:1rem 1.25rem;">
        <h1 class="studio-section-title" style="text-align:center;margin-bottom:1rem;">Review Table</h1>
        <table class="zc-sm-tbl">
            <thead><tr><th>#</th><th>Product</th><th>User Name</th><th>Review</th><th>Rating</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
                @forelse ($reviews as $review)
                    @php $on = $review->status === 'approved'; @endphp
                    <tr>
                        <td>{{ $loop->iteration + ($reviews->firstItem() ? $reviews->firstItem() - 1 : 0) }}</td>
                        <td>
                            <div class="zc-sm-prod">
                                <span class="zc-sm-thumb">@if($review->product && $mediaUrl($review->product->thumbnail))<img src="{{ $mediaUrl($review->product->thumbnail) }}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:9px;">@else<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"><path d="M5 21V9l7-5 7 5v12"/></svg>@endif</span>
                                <span class="zc-sm-name">{{ $review->product->name ?? 'Product' }}</span>
                            </div>
                        </td>
                        <td>{{ $review->reviewer_name ?: 'Anonymous' }}</td>
                        <td style="max-width:260px;">{{ \Illuminate\Support\Str::limit($review->title ?: $review->body, 60) ?: '—' }}</td>
                        <td><span class="zc-sm-stars">{{ str_repeat('★', (int) $review->rating) }}<span style="color:var(--studio-border)">{{ str_repeat('★', 5 - (int) $review->rating) }}</span></span></td>
                        <td><span class="zc-sm-pill {{ $on ? 'zc-sm-pill--on' : 'zc-sm-pill--off' }}">{{ $on ? 'Active' : 'De-active' }}</span></td>
                        <td>
                            <div class="zc-sm-act">
                                <form method="POST" action="{{ route('products.reviews.toggle', $review) }}">@csrf<button class="zc-sm-btn {{ $on ? 'zc-sm-btn--tog' : 'zc-sm-btn--ok' }}" title="{{ $on ? 'Deactivate' : 'Approve' }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M20 6 9 17l-5-5"/></svg></button></form>
                                <form method="POST" action="{{ route('products.reviews.destroy', $review) }}" onsubmit="return confirm('Delete this review?')">@csrf @method('DELETE')<button class="zc-sm-btn zc-sm-btn--del" title="Delete"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M9 7V5h6v2M7 7l1 13h8l1-13"/></svg></button></form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="zc-sm-empty">No product reviews yet. Verified buyers can submit reviews after delivery.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($reviews->hasPages())<div class="zc-sm-pager">@if(!$reviews->onFirstPage())<a href="{{ $reviews->previousPageUrl() }}" class="studio-command-button">Prev</a>@endif<span>Page {{ $reviews->currentPage() }} / {{ $reviews->lastPage() }}</span>@if($reviews->hasMorePages())<a href="{{ $reviews->nextPageUrl() }}" class="studio-command-button studio-command-button--primary">Next</a>@endif</div>@endif
    </div>
</div>
@endsection
