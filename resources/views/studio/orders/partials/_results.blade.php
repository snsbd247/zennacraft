{{-- Orders table + pager — rendered standalone for AJAX search (region swap). --}}
<div class="studio-responsive-scroll">
    <table class="zc-ol-tbl zc-ol-tbl--cards">
        <thead>
            <tr>
                <th><input type="checkbox" class="zc-ol-check" data-check-all></th>
                <th>Invoice</th><th>Customer</th><th>Product</th><th>Total</th>
                <th>Activities</th><th>Call Verification</th><th>Courier</th><th>Note</th><th>Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($orders as $order)
                @include('studio.orders.partials._row', ['order' => $order, 'mediaUrl' => $mediaUrl, 'couriers' => $couriers, 'commentPresets' => $commentPresets])
            @empty
                <tr><td colspan="10"><div class="zc-op-empty">No orders match these filters.</div></td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@if ($orders->hasPages())
    <div class="p-4">{{ $orders->appends($filters + ['per_page' => $perPage])->links() }}</div>
@endif
