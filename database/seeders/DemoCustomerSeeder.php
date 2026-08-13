<?php

namespace Database\Seeders;

use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerSegment;
use App\Modules\Marketing\Models\MarketingSegment;
use App\Modules\Marketing\Models\MarketingSegmentMembership;
use App\Modules\RTO\Models\CustomerRiskProfile;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DemoCustomerSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $customers = [
            ['Demo New Customer', '01000000001', 'Mirpur, Dhaka', 'NEW', 'low', ['new_customer']],
            ['Demo Repeat Customer', '01000000002', 'Dhanmondi, Dhaka', 'REGULAR', 'low', ['repeat_customer']],
            ['Demo VIP Customer', '01000000003', 'Gulshan, Dhaka', 'VIP', 'low', ['vip_customer', 'high_value_customer']],
            ['Demo Dormant Customer', '01000000004', 'Cumilla Sadar, Cumilla', 'AT_RISK', 'medium', ['dormant_customer', 'at_risk_customer']],
            ['Demo Coupon Lover', '01000000005', 'Sylhet Sadar, Sylhet', 'REGULAR', 'low', ['coupon_lover']],
            ['Demo Recovery Customer', '01000000006', 'Rajshahi, Rajshahi', 'NEW', 'medium', ['abandoned_checkout_customer']],
            ['Demo High Risk Customer', '01000000007', 'Cox Bazar, Chattogram', 'AT_RISK', 'high', ['fraud_risk_customer', 'at_risk_customer']],
            ['Demo Gift Buyer', '01000000008', 'Uttara, Dhaka', 'LOYAL', 'low', ['repeat_customer', 'coupon_lover']],
        ];

        foreach ($customers as $index => [$name, $phone, $address, $segment, $riskLevel, $marketingSegments]) {
            $customer = Customer::updateOrCreate(
                ['phone' => $phone],
                [
                    'name' => $name,
                    'email' => 'demo.customer.'.($index + 1).'@example.test',
                    'address' => $address.' | DEMO-QA',
                    'cod_score' => $riskLevel === 'high' ? 42 : ($riskLevel === 'medium' ? 68 : 90),
                ]
            );

            CustomerSegment::updateOrCreate(
                ['customer_id' => $customer->id],
                [
                    'segment' => $segment,
                    'lifetime_value' => 0,
                    'total_orders' => 0,
                    'delivered_orders' => 0,
                    'cancelled_orders' => 0,
                    'returned_orders' => 0,
                    'last_order_at' => null,
                    'last_calculated_at' => now(),
                ]
            );

            CustomerRiskProfile::updateOrCreate(
                ['customer_id' => $customer->id],
                [
                    'risk_score' => $riskLevel === 'high' ? 82 : ($riskLevel === 'medium' ? 58 : 18),
                    'risk_level' => $riskLevel,
                    'total_orders' => 0,
                    'delivered_orders' => 0,
                    'cancelled_orders' => 0,
                    'returned_orders' => 0,
                    'delivery_rate' => $riskLevel === 'high' ? 38 : ($riskLevel === 'medium' ? 70 : 96),
                    'cancel_rate' => $riskLevel === 'high' ? 36 : ($riskLevel === 'medium' ? 14 : 2),
                    'return_rate' => $riskLevel === 'high' ? 26 : ($riskLevel === 'medium' ? 16 : 2),
                    'last_calculated_at' => now(),
                ]
            );

            foreach ($marketingSegments as $slug) {
                $segmentModel = MarketingSegment::query()
                    ->whereIn('slug', [$slug, strtoupper($slug)])
                    ->first();

                if (! $segmentModel) {
                    continue;
                }

                MarketingSegmentMembership::withoutAutomation(function () use ($segmentModel, $customer): void {
                    MarketingSegmentMembership::updateOrCreate(
                        [
                            'marketing_segment_id' => $segmentModel->id,
                            'customer_id' => $customer->id,
                        ],
                        [
                            'joined_at' => now()->subDays(20),
                            'last_evaluated_at' => now(),
                        ]
                    );
                });
            }
        }
    }
}
