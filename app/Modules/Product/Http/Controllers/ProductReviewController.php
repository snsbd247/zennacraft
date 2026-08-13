<?php

namespace App\Modules\Product\Http\Controllers;

use App\Modules\Media\Services\MediaService;
use App\Modules\Review\Models\ProductReview;
use App\Modules\Review\Services\ProductReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProductReviewController extends Controller
{
    public function __construct(private ProductReviewService $reviews) {}

    public function index(): View
    {
        return view('studio.products.reviews.index', [
            'reviews' => ProductReview::with('product.thumbnail')->latest()->paginate(20),
            'mediaUrl' => fn ($media) => $media ? app(MediaService::class)->url($media) : null,
        ]);
    }

    /**
     * Approve publishes the review to the storefront; toggling an approved
     * review takes it back down (rejected). Only approved reviews are ever
     * shown on the storefront. approve()/reject() set approved_at, run review
     * automation, and flush the storefront review cache via the model observer.
     */
    public function toggleStatus(ProductReview $review): RedirectResponse
    {
        $staff = Auth::guard('staff')->user();

        if ($review->status === ProductReview::STATUS_APPROVED) {
            $this->reviews->reject($review, $staff);
            $message = 'Review unpublished — it is no longer shown on the storefront.';
        } else {
            $this->reviews->approve($review, $staff);
            $message = 'Review approved — it is now live on the storefront.';
        }

        return back()->with('success', $message);
    }

    public function destroy(ProductReview $review): RedirectResponse
    {
        $review->delete();

        return back()->with('success', 'Review deleted.');
    }
}
