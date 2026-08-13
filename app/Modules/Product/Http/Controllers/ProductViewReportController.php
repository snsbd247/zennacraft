<?php

namespace App\Modules\Product\Http\Controllers;

use App\Modules\Analytics\Models\CustomerBehaviorEvent;
use App\Modules\Media\Models\Media;
use App\Modules\Media\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class ProductViewReportController extends Controller
{
    public function index(Request $request): View
    {
        $from = $request->query('from');
        $to = $request->query('to');
        $q = trim((string) $request->query('q', ''));

        $rows = CustomerBehaviorEvent::query()
            ->where('customer_behavior_events.event_type', 'product_viewed')
            ->join('products', 'products.id', '=', 'customer_behavior_events.product_id')
            ->when($from, fn ($query) => $query->whereDate('customer_behavior_events.occurred_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('customer_behavior_events.occurred_at', '<=', $to))
            ->when($q !== '', fn ($query) => $query->where('products.name', 'like', '%'.$q.'%'))
            ->selectRaw('products.id as product_id, products.name as product_name, products.thumbnail_id, count(*) as views, max(customer_behavior_events.occurred_at) as last_viewed')
            ->groupBy('products.id', 'products.name', 'products.thumbnail_id')
            ->orderByDesc('views')
            ->paginate(20)
            ->withQueryString();

        $mediaService = app(MediaService::class);
        $thumbs = Media::whereIn('id', collect($rows->items())->pluck('thumbnail_id')->filter()->unique())
            ->get()->mapWithKeys(fn ($m) => [$m->id => $mediaService->url($m)]);

        return view('studio.products.view-report.index', compact('rows', 'thumbs', 'from', 'to', 'q'));
    }
}
