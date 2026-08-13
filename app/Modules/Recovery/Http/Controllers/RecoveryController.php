<?php

namespace App\Modules\Recovery\Http\Controllers;

use App\Modules\Media\Services\MediaService;
use App\Modules\Recovery\Models\CheckoutRecovery;
use App\Modules\Recovery\Services\RecoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Studio "Incomplete Orders" (Customer group): the customers who filled in
 * their checkout details but never clicked Confirm. Reads the checkout_recovery
 * rows captured live by CheckoutController::captureRecovery(), so the owner can
 * see the entered name/phone/address and follow up (call / WhatsApp / mark).
 */
class RecoveryController extends Controller
{
    public function __construct(private RecoveryService $recovery, private MediaService $media) {}

    public function index(Request $request): View
    {
        $tab = in_array($request->query('tab'), ['incomplete', 'abandoned', 'callback', 'recovered', 'all'], true)
            ? (string) $request->query('tab') : 'incomplete';
        $term = trim((string) $request->query('q', ''));
        $active = RecoveryService::activeStatuses();

        $recoveries = CheckoutRecovery::with(['product.thumbnail', 'variant:id,name'])
            ->when($tab === 'incomplete', fn ($q) => $q->whereIn('status', $active)
                ->where(fn ($w) => $w->whereNotNull('customer_phone')->orWhereNotNull('customer_name')->orWhereNotNull('address')))
            ->when($tab === 'abandoned', fn ($q) => $q->whereIn('status', $active)
                ->whereNull('customer_phone')->whereNull('customer_name')->whereNull('address'))
            ->when($tab === 'callback', fn ($q) => $q->whereIn('status', $active)->whereNotNull('customer_phone'))
            ->when($tab === 'recovered', fn ($q) => $q->where('status', 'recovered'))
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('customer_name', 'like', '%'.$term.'%')
                ->orWhere('customer_phone', 'like', '%'.$term.'%')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('studio.recoveries.index', [
            'recoveries' => $recoveries,
            'tab' => $tab,
            'term' => $term,
            'stats' => $this->stats(),
            'statuses' => $this->actionableStatuses(),
            'mediaUrl' => fn ($media) => $media ? $this->media->url($media) : null,
        ]);
    }

    public function show(CheckoutRecovery $recovery): View
    {
        return view('studio.recoveries.show', [
            'recovery' => $recovery->load(['product.thumbnail', 'variant:id,name']),
            'statuses' => $this->actionableStatuses(),
            'mediaUrl' => fn ($media) => $media ? $this->media->url($media) : null,
        ]);
    }

    public function updateStatus(Request $request, CheckoutRecovery $recovery): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(RecoveryService::statuses())],
        ]);
        $this->recovery->markStatus($recovery, $data['status']);

        return response()->json(['message' => 'Status updated.', 'status' => $data['status']]);
    }

    /** Lightweight counts for the stat strip (no per-row scoring). */
    private function stats(): array
    {
        $active = RecoveryService::activeStatuses();
        $withDetails = fn ($q) => $q->where(fn ($w) => $w->whereNotNull('customer_phone')->orWhereNotNull('customer_name')->orWhereNotNull('address'));

        return [
            'incomplete' => (int) CheckoutRecovery::whereIn('status', $active)->where($withDetails)->count(),
            'today' => (int) CheckoutRecovery::whereIn('status', $active)->where($withDetails)->whereDate('created_at', today())->count(),
            'callable' => (int) CheckoutRecovery::whereIn('status', $active)->whereNotNull('customer_phone')->count(),
            'recovered' => (int) CheckoutRecovery::where('status', 'recovered')->count(),
        ];
    }

    /** Statuses the owner sets by hand while following up. */
    private function actionableStatuses(): array
    {
        return [
            'called' => 'Called',
            'no_answer' => 'No answer',
            'interested' => 'Interested',
            'not_interested' => 'Not interested',
            'recovered' => 'Recovered',
            'lost' => 'Lost',
        ];
    }
}
