<?php

namespace Database\Seeders;

use App\Modules\Order\Models\Order;
use App\Modules\Review\Models\ProductReview;
use Database\Seeders\Concerns\SeedsDemoData;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DemoReviewSeeder extends Seeder
{
    use SeedsDemoData;
    use WithoutModelEvents;

    public function run(): void
    {
        $orders = Order::query()
            ->where('order_number', 'like', 'DEMO-ORD-%')
            ->where('status', 'delivered')
            ->with(['items.product', 'items.variant', 'customer'])
            ->orderBy('id')
            ->get();

        if ($orders->isEmpty()) {
            return;
        }

        ProductReview::query()
            ->whereIn('order_id', $orders->pluck('id'))
            ->delete();

        $statuses = [
            ProductReview::STATUS_APPROVED,
            ProductReview::STATUS_APPROVED,
            ProductReview::STATUS_PENDING,
            ProductReview::STATUS_REJECTED,
            ProductReview::STATUS_APPROVED,
        ];
        $ratings = [5, 5, 4, 3, 4, 5, 4, 5];
        $created = 0;

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                if (! $item->product || $created >= 12) {
                    continue;
                }

                $status = $statuses[$created % count($statuses)];
                $approved = $status === ProductReview::STATUS_APPROVED;

                ProductReview::create([
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->variant_id,
                    'customer_id' => $order->customer_id,
                    'order_id' => $order->id,
                    'rating' => $ratings[$created % count($ratings)],
                    'title' => $approved ? 'Demo verified craft review' : 'Demo moderation review',
                    'body' => $approved
                        ? 'Demo QA review from a delivered order to verify public review display and rating summaries.'
                        : 'Demo QA review used for moderation testing. It should not affect public rating unless approved.',
                    'status' => $status,
                    'is_verified_purchase' => true,
                    'is_featured' => $approved && $created % 3 === 0,
                    'reviewer_name' => $order->customer?->name ?? 'Demo Customer',
                    'reviewer_location' => 'Demo District',
                    'moderation_note' => $status === ProductReview::STATUS_REJECTED ? 'DEMO rejected review for moderation QA.' : null,
                    'approved_by' => $approved ? $this->demoStaffId() : null,
                    'approved_at' => $approved ? now()->subDays(4 - ($created % 3)) : null,
                ]);

                $created++;
            }
        }
    }
}
