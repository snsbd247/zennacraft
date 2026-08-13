<?php

namespace Database\Seeders;

use App\Modules\Communication\Models\CommunicationMessage;
use App\Modules\Customer\Models\Customer;
use App\Modules\Marketing\Models\MarketingCampaign;
use App\Modules\Marketing\Models\MarketingCampaignLog;
use App\Modules\Marketing\Models\MarketingSegment;
use App\Modules\Marketing\Models\MarketingSegmentMembership;
use App\Modules\Order\Models\Order;
use App\Modules\Promotion\Models\Coupon;
use App\Modules\Promotion\Models\CouponUsage;
use Database\Seeders\Concerns\SeedsDemoData;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DemoMarketingSeeder extends Seeder
{
    use SeedsDemoData;
    use WithoutModelEvents;

    public function run(): void
    {
        $this->ensureCoupons();
        $this->refreshSegmentMemberships();
        $campaigns = $this->campaigns();
        $this->logs($campaigns);
        $this->messages($campaigns);
    }

    protected function ensureCoupons(): void
    {
        Coupon::updateOrCreate(
            ['code' => 'DEMO-VIP'],
            [
                'name' => 'Demo VIP Loyalty',
                'description' => 'Demo QA coupon for VIP campaign testing.',
                'discount_type' => 'fixed',
                'discount_value' => 250,
                'max_discount_amount' => null,
                'min_order_amount' => 1800,
                'usage_limit' => 100,
                'usage_limit_per_customer' => 2,
                'starts_at' => now()->subWeeks(2),
                'ends_at' => now()->addMonth(),
                'status' => 'active',
                'applies_to' => 'all',
                'created_by' => $this->demoStaffId(),
            ]
        );
    }

    protected function refreshSegmentMemberships(): void
    {
        $customers = Customer::query()->where('phone', 'like', '0100000000%')->get();
        $segments = MarketingSegment::query()->get()->keyBy(fn (MarketingSegment $segment): string => strtoupper($segment->slug));

        $mapping = [
            '01000000001' => ['NEW_CUSTOMER'],
            '01000000002' => ['REPEAT_CUSTOMER'],
            '01000000003' => ['VIP_CUSTOMER', 'HIGH_VALUE_CUSTOMER'],
            '01000000004' => ['DORMANT_CUSTOMER', 'AT_RISK_CUSTOMER'],
            '01000000005' => ['COUPON_LOVER', 'REPEAT_CUSTOMER'],
            '01000000006' => ['ABANDONED_CHECKOUT_CUSTOMER'],
            '01000000007' => ['FRAUD_RISK_CUSTOMER', 'AT_RISK_CUSTOMER'],
            '01000000008' => ['COUPON_LOVER', 'REPEAT_CUSTOMER'],
        ];

        foreach ($customers as $customer) {
            foreach ($mapping[$customer->phone] ?? [] as $slug) {
                $segment = $segments->get($slug);

                if (! $segment) {
                    continue;
                }

                MarketingSegmentMembership::withoutAutomation(function () use ($segment, $customer): void {
                    MarketingSegmentMembership::updateOrCreate(
                        [
                            'marketing_segment_id' => $segment->id,
                            'customer_id' => $customer->id,
                        ],
                        [
                            'joined_at' => now()->subDays(18),
                            'last_evaluated_at' => now(),
                        ]
                    );
                });
            }
        }
    }

    protected function campaigns(): array
    {
        $vipSegment = MarketingSegment::query()->whereIn('slug', ['VIP_CUSTOMER', 'vip_customer'])->first();
        $recoverySegment = MarketingSegment::query()->whereIn('slug', ['ABANDONED_CHECKOUT_CUSTOMER', 'abandoned_checkout_customer'])->first();
        $orders = Order::query()->where('order_number', 'like', 'DEMO-ORD-%')->get();
        $couponRevenue = CouponUsage::query()
            ->whereHas('order', fn ($query) => $query->where('order_number', 'like', 'DEMO-ORD-%'))
            ->sum('discount_amount');

        $definitions = [
            [
                'name' => 'Demo Nokshi Launch Campaign',
                'slug' => 'demo-nokshi-launch',
                'description' => 'Demo campaign for launch attribution and campaign command center QA.',
                'campaign_type' => 'product_campaign',
                'status' => 'running',
                'audience_type' => 'all_customers',
                'marketing_segment_id' => null,
                'template_key' => 'demo_product_launch',
                'total_converted' => $orders->where('status', 'delivered')->count(),
                'total_revenue' => $orders->where('status', 'delivered')->sum('total'),
            ],
            [
                'name' => 'Demo VIP Thank You',
                'slug' => 'demo-vip-thank-you',
                'description' => 'Demo VIP campaign for customer intelligence QA.',
                'campaign_type' => 'vip_campaign',
                'status' => 'completed',
                'audience_type' => 'segment',
                'marketing_segment_id' => $vipSegment?->id,
                'template_key' => 'demo_vip_thank_you',
                'total_converted' => 3,
                'total_revenue' => max(0, ((float) $orders->where('status', 'delivered')->sum('total')) * 0.22),
            ],
            [
                'name' => 'Demo Recovery Winback',
                'slug' => 'demo-recovery-winback',
                'description' => 'Demo abandoned checkout campaign for recovery attribution QA.',
                'campaign_type' => 'abandoned_checkout_campaign',
                'status' => 'scheduled',
                'audience_type' => 'segment',
                'marketing_segment_id' => $recoverySegment?->id,
                'template_key' => 'demo_recovery_winback',
                'total_converted' => 2,
                'total_revenue' => max(0, $couponRevenue * 7),
            ],
        ];

        $campaigns = [];

        foreach ($definitions as $index => $definition) {
            $campaigns[] = MarketingCampaign::updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'campaign_type' => $definition['campaign_type'],
                    'status' => $definition['status'],
                    'audience_type' => $definition['audience_type'],
                    'marketing_segment_id' => $definition['marketing_segment_id'],
                    'audience_json' => ['demo' => true, 'sources' => ['facebook', 'instagram', 'whatsapp']],
                    'template_key' => $definition['template_key'],
                    'starts_at' => now()->subDays(12 - $index),
                    'ends_at' => $definition['status'] === 'scheduled' ? now()->addDays(7) : now()->addDays(14),
                    'total_recipients' => 20 + ($index * 8),
                    'total_queued' => 18 + ($index * 7),
                    'total_sent' => 15 + ($index * 6),
                    'total_opened' => 9 + ($index * 4),
                    'total_clicked' => 5 + ($index * 3),
                    'total_converted' => $definition['total_converted'],
                    'total_revenue' => $definition['total_revenue'],
                    'created_by' => $this->demoStaffId(),
                ]
            );
        }

        return $campaigns;
    }

    protected function logs(array $campaigns): void
    {
        $ids = collect($campaigns)->pluck('id');

        MarketingCampaignLog::query()->whereIn('marketing_campaign_id', $ids)->delete();

        foreach ($campaigns as $index => $campaign) {
            foreach (['queued', 'engaged', 'converted'] as $offset => $type) {
                MarketingCampaignLog::create([
                    'marketing_campaign_id' => $campaign->id,
                    'log_type' => $type,
                    'message' => 'DEMO QA campaign '.$type.' event for reporting verification.',
                    'metadata' => [
                        'campaign_id' => $campaign->id,
                        'demo' => true,
                        'source' => ['facebook', 'instagram', 'whatsapp'][$offset],
                        'count' => 2 + $index + $offset,
                    ],
                    'created_at' => now()->subDays(5 - $offset),
                ]);
            }
        }
    }

    protected function messages(array $campaigns): void
    {
        CommunicationMessage::query()
            ->whereIn('template', ['demo_product_launch', 'demo_vip_thank_you', 'demo_recovery_winback'])
            ->delete();

        $customers = Customer::query()->where('phone', 'like', '0100000000%')->take(6)->get();

        foreach ($campaigns as $campaign) {
            foreach ($customers as $customer) {
                CommunicationMessage::create([
                    'customer_id' => $customer->id,
                    'channel' => 'internal',
                    'recipient' => null,
                    'recipient_hash' => null,
                    'template' => $campaign->template_key,
                    'subject' => 'DEMO QA campaign message',
                    'body' => 'Demo campaign queue record only. No external sending is performed.',
                    'variables' => [
                        'campaign_id' => $campaign->id,
                        'customer_id' => $customer->id,
                        'demo' => true,
                    ],
                    'status' => 'queued',
                    'queued_at' => now()->subDays(2),
                ]);
            }
        }
    }
}
