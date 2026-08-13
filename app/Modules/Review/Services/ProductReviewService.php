<?php

namespace App\Modules\Review\Services;

use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Automation\Services\AutomationEngineService;
use App\Modules\Customer\Models\Customer;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderItem;
use App\Modules\Performance\Services\CacheService;
use App\Modules\Performance\Support\CacheKeyRegistry;
use App\Modules\Product\Models\Product;
use App\Modules\Review\Models\ProductReview;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProductReviewService
{
    public function __construct(private CacheService $cacheService) {}

    public function productSummary(Product $product, int $limit = 8): array
    {
        return $this->cacheService->remember(
            CacheKeyRegistry::productReviewSummary((int) $product->id),
            fn (): array => $this->buildProductSummary($product, $limit),
            600,
            [CacheKeyRegistry::REVIEWS_TAG]
        );
    }

    public function featuredReviews(int $limit = 3): Collection
    {
        return collect($this->cacheService->remember(
            CacheKeyRegistry::FEATURED_PRODUCT_REVIEWS,
            fn (): array => ProductReview::query()
                ->approved()
                ->where('is_featured', true)
                ->where('is_verified_purchase', true)
                ->with(['product:id,name,slug', 'variant:id,name'])
                ->latest('approved_at')
                ->latest()
                ->limit($limit)
                ->get()
                ->map(fn (ProductReview $review): array => $this->reviewCard($review))
                ->all(),
            600,
            [CacheKeyRegistry::REVIEWS_TAG]
        ));
    }

    public function reviewableOrderItems(Customer $customer, Order $order): Collection
    {
        if (! $this->ownsOrder($customer, $order) || ! $this->isDelivered($order)) {
            return collect();
        }

        $order->loadMissing(['items.product.thumbnail', 'items.variant']);
        $existing = ProductReview::query()
            ->where('customer_id', $customer->id)
            ->where('order_id', $order->id)
            ->get(['product_id', 'product_variant_id'])
            ->map(fn (ProductReview $review): string => $this->reviewKey((int) $review->product_id, $review->product_variant_id ? (int) $review->product_variant_id : null))
            ->all();

        return $order->items
            ->filter(function (OrderItem $item) use ($existing): bool {
                return ! in_array($this->reviewKey((int) $item->product_id, $item->variant_id ? (int) $item->variant_id : null), $existing, true);
            })
            ->values();
    }

    public function submit(Customer $customer, Order $order, array $data): ProductReview
    {
        $item = $this->ensureCanReview(
            $customer,
            $order,
            (int) $data['product_id'],
            isset($data['variant_id']) ? (int) $data['variant_id'] : null
        );

        return ProductReview::create([
            'product_id' => $item->product_id,
            'product_variant_id' => $item->variant_id,
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'rating' => (int) $data['rating'],
            'title' => $this->cleanText($data['title'] ?? null, 120),
            'body' => $this->cleanText($data['body'] ?? null, 2000),
            'reviewer_name' => $this->cleanText($data['reviewer_name'] ?? $customer->name, 120),
            'reviewer_location' => $this->cleanText($data['reviewer_location'] ?? null, 120),
            'status' => ProductReview::STATUS_PENDING,
            'is_verified_purchase' => true,
            'is_featured' => false,
        ]);
    }

    public function approve(ProductReview $review, ?StaffUser $staffUser = null, ?string $note = null): ProductReview
    {
        $review->forceFill([
            'status' => ProductReview::STATUS_APPROVED,
            'approved_by' => $staffUser?->id,
            'approved_at' => now(),
            'moderation_note' => $this->cleanText($note, 1000),
        ])->save();

        $this->handleReviewAutomation($review);

        return $review->refresh();
    }

    public function reject(ProductReview $review, ?StaffUser $staffUser = null, ?string $note = null): ProductReview
    {
        $review->forceFill([
            'status' => ProductReview::STATUS_REJECTED,
            'is_featured' => false,
            'approved_by' => $staffUser?->id,
            'approved_at' => null,
            'moderation_note' => $this->cleanText($note, 1000),
        ])->save();

        return $review->refresh();
    }

    public function feature(ProductReview $review): ProductReview
    {
        if ($review->status !== ProductReview::STATUS_APPROVED) {
            throw ValidationException::withMessages([
                'review' => 'Only approved reviews can be featured.',
            ]);
        }

        $review->forceFill(['is_featured' => true])->save();

        return $review->refresh();
    }

    public function unfeature(ProductReview $review): ProductReview
    {
        $review->forceFill(['is_featured' => false])->save();

        return $review->refresh();
    }

    public function loyaltyProfile(Customer $customer): array
    {
        $orderStats = $customer->orders()
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw("SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'delivered' THEN total ELSE 0 END), 0) as lifetime_spend")
            ->selectRaw("SUM(CASE WHEN status IN ('cancelled', 'returned') THEN 1 ELSE 0 END) as returned_or_cancelled")
            ->first();
        $reviewStats = $customer->productReviews()
            ->selectRaw('COUNT(*) as review_count')
            ->selectRaw('COALESCE(AVG(rating), 0) as average_rating')
            ->first();
        $deliveredCount = (int) ($orderStats?->delivered_orders ?? 0);
        $lifetimeSpend = (float) ($orderStats?->lifetime_spend ?? 0);
        $returnedOrCancelled = (int) ($orderStats?->returned_or_cancelled ?? 0);
        $reviewCount = (int) ($reviewStats?->review_count ?? 0);
        $averageRating = (float) ($reviewStats?->average_rating ?? 0);
        $tier = $this->tierFor($deliveredCount, $lifetimeSpend);

        return [
            'tier' => $tier,
            'next_milestone' => $this->nextMilestone($tier, $deliveredCount, $lifetimeSpend),
            'delivered_orders' => $deliveredCount,
            'lifetime_spend' => $lifetimeSpend,
            'total_orders' => (int) ($orderStats?->total_orders ?? 0),
            'cancelled_or_returned' => $returnedOrCancelled,
            'review_count' => $reviewCount,
            'average_rating_given' => round($averageRating, 2),
            'repeat_purchase_signal' => $deliveredCount >= 2 ? 'Repeat buyer' : 'Building history',
            'trust_level' => $this->trustLevel($deliveredCount, $returnedOrCancelled, $reviewCount),
            'badges' => $this->customerBadges($tier, $deliveredCount, $reviewCount),
            'suggested_action' => $this->suggestedAction($tier, $deliveredCount, $reviewCount),
        ];
    }

    public function reviewMetrics(): array
    {
        $approved = ProductReview::query()->approved();
        $reviewCount = (int) ProductReview::query()->count();
        $approvedCount = (int) (clone $approved)->count();
        $deliveredOrders = (int) Order::query()->where('status', 'delivered')->count();
        $reviewedOrders = (int) ProductReview::query()->whereNotNull('order_id')->distinct('order_id')->count('order_id');
        $loyaltyDistribution = $this->loyaltyDistribution();

        return [
            'average_rating' => round((float) (clone $approved)->avg('rating'), 2),
            'review_count' => $reviewCount,
            'approved_review_count' => $approvedCount,
            'pending_review_count' => (int) ProductReview::query()->pending()->count(),
            'featured_review_count' => (int) ProductReview::query()->approved()->where('is_featured', true)->count(),
            'verified_review_count' => (int) ProductReview::query()->where('is_verified_purchase', true)->count(),
            'review_conversion_rate' => $deliveredOrders > 0 ? round(($reviewedOrders / $deliveredOrders) * 100, 2) : 0.0,
            'loyalty_tier_distribution' => $loyaltyDistribution,
            'vip_customers' => (int) ($loyaltyDistribution['VIP'] ?? 0),
        ];
    }

    public function eligibleCustomerAction(Customer $customer): string
    {
        return $this->loyaltyProfile($customer)['suggested_action'];
    }

    protected function buildProductSummary(Product $product, int $limit): array
    {
        $approvedQuery = ProductReview::query()
            ->approved()
            ->where('product_id', $product->id);

        $reviews = (clone $approvedQuery)
            ->with(['variant:id,name'])
            ->latest('approved_at')
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (ProductReview $review): array => $this->reviewCard($review))
            ->all();

        $featuredReview = (clone $approvedQuery)
            ->where('is_featured', true)
            ->where('is_verified_purchase', true)
            ->latest('approved_at')
            ->first();

        return [
            'average_rating' => round((float) (clone $approvedQuery)->avg('rating'), 2),
            'review_count' => (int) (clone $approvedQuery)->count(),
            'distribution' => $this->ratingDistribution($product),
            'featured_review' => $featuredReview ? $this->reviewCard($featuredReview) : null,
            'reviews' => $reviews,
        ];
    }

    protected function ratingDistribution(Product $product): array
    {
        $counts = ProductReview::query()
            ->approved()
            ->where('product_id', $product->id)
            ->select('rating', DB::raw('COUNT(*) as total'))
            ->groupBy('rating')
            ->pluck('total', 'rating')
            ->map(fn ($value): int => (int) $value)
            ->all();

        return collect(range(5, 1))
            ->mapWithKeys(fn (int $rating): array => [$rating => $counts[$rating] ?? 0])
            ->all();
    }

    protected function reviewCard(ProductReview $review): array
    {
        return [
            'id' => $review->id,
            'rating' => (int) $review->rating,
            'title' => $review->title,
            'body' => $review->body,
            'reviewer_name' => $review->reviewer_name ?: 'Verified customer',
            'reviewer_location' => $review->reviewer_location,
            'is_verified_purchase' => (bool) $review->is_verified_purchase,
            'is_featured' => (bool) $review->is_featured,
            'approved_at' => $review->approved_at,
            'product_name' => $review->product?->name,
            'product_slug' => $review->product?->slug,
            'variant_name' => $review->variant?->name,
        ];
    }

    protected function ensureCanReview(Customer $customer, Order $order, int $productId, ?int $variantId): OrderItem
    {
        if (! $this->ownsOrder($customer, $order)) {
            throw ValidationException::withMessages([
                'order' => 'This order is not available for review.',
            ]);
        }

        if (! $this->isDelivered($order)) {
            throw ValidationException::withMessages([
                'order' => 'Reviews are available after delivery.',
            ]);
        }

        $order->loadMissing('items');

        $item = $order->items->first(function (OrderItem $item) use ($productId, $variantId): bool {
            if ((int) $item->product_id !== $productId) {
                return false;
            }

            return $variantId === null || (int) $item->variant_id === $variantId;
        });

        if (! $item) {
            throw ValidationException::withMessages([
                'product_id' => 'This product is not part of the delivered order.',
            ]);
        }

        $exists = ProductReview::query()
            ->where('customer_id', $customer->id)
            ->where('order_id', $order->id)
            ->where('product_id', $item->product_id)
            ->when($item->variant_id, fn (Builder $query) => $query->where('product_variant_id', $item->variant_id), fn (Builder $query) => $query->whereNull('product_variant_id'))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'product_id' => 'A review has already been submitted for this product in this order.',
            ]);
        }

        return $item;
    }

    protected function ownsOrder(Customer $customer, Order $order): bool
    {
        return (int) $order->customer_id === (int) $customer->id;
    }

    protected function isDelivered(Order $order): bool
    {
        return $order->status === 'delivered';
    }

    protected function cleanText(?string $value, int $limit): ?string
    {
        $clean = trim(strip_tags((string) $value));

        return $clean === '' ? null : Str::limit($clean, $limit, '');
    }

    protected function reviewKey(int $productId, ?int $variantId): string
    {
        return $productId.':'.($variantId ?: 'base');
    }

    protected function tierFor(int $deliveredOrders, float $lifetimeSpend): string
    {
        return match (true) {
            $deliveredOrders >= 5 || $lifetimeSpend >= 20000 => 'VIP',
            $deliveredOrders >= 3 || $lifetimeSpend >= 10000 => 'Loyal',
            $deliveredOrders >= 2 => 'Repeat',
            default => 'New',
        };
    }

    protected function nextMilestone(string $tier, int $deliveredOrders, float $lifetimeSpend): string
    {
        return match ($tier) {
            'VIP' => 'You are in the highest loyalty tier.',
            'Loyal' => 'Reach 5 delivered orders or 20,000 lifetime spend for VIP.',
            'Repeat' => 'Reach 3 delivered orders or 10,000 lifetime spend for Loyal.',
            default => 'Place and receive 2 orders to become a Repeat customer.',
        };
    }

    protected function trustLevel(int $deliveredOrders, int $returnedOrCancelled, int $reviewCount): string
    {
        if ($deliveredOrders >= 5 && $returnedOrCancelled <= 1) {
            return 'High Trust';
        }

        if ($deliveredOrders >= 2 || $reviewCount > 0) {
            return 'Trusted';
        }

        return 'Building Trust';
    }

    protected function customerBadges(string $tier, int $deliveredOrders, int $reviewCount): array
    {
        return array_values(array_filter([
            $deliveredOrders > 0 ? 'Verified Buyer' : null,
            $deliveredOrders >= 2 ? 'Repeat Customer' : null,
            $tier === 'VIP' ? 'VIP Customer' : null,
            $reviewCount > 0 ? 'Reviewer' : null,
        ]));
    }

    protected function suggestedAction(string $tier, int $deliveredOrders, int $reviewCount): string
    {
        if ($deliveredOrders > 0 && $reviewCount === 0) {
            return 'Ask for review';
        }

        if ($tier === 'VIP') {
            return 'VIP campaign';
        }

        if ($reviewCount > 0) {
            return 'Send thank-you';
        }

        if ($deliveredOrders >= 2) {
            return 'Offer loyalty coupon';
        }

        return 'No action';
    }

    protected function loyaltyDistribution(): array
    {
        $distribution = ['New' => 0, 'Repeat' => 0, 'Loyal' => 0, 'VIP' => 0];

        Customer::query()
            ->select(['id'])
            ->with(['orders:id,customer_id,status,total'])
            ->chunkById(500, function (Collection $customers) use (&$distribution): void {
                foreach ($customers as $customer) {
                    $delivered = $customer->orders->where('status', 'delivered');
                    $tier = $this->tierFor($delivered->count(), (float) $delivered->sum('total'));
                    $distribution[$tier] = ($distribution[$tier] ?? 0) + 1;
                }
            });

        return $distribution;
    }

    protected function handleReviewAutomation(ProductReview $review): void
    {
        try {
            app(AutomationEngineService::class)->handleTrigger('review.approved', $review, [
                'review_id' => $review->id,
                'customer_id' => $review->customer_id,
                'product_id' => $review->product_id,
                'order_id' => $review->order_id,
            ]);
        } catch (Throwable) {
            // Automation hooks are non-blocking and should not interrupt moderation.
        }
    }
}
