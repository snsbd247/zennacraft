<?php

namespace Database\Seeders;

use App\Modules\Order\Models\Order;
use App\Modules\Verification\Models\OrderVerificationAttempt;
use Database\Seeders\Concerns\SeedsDemoData;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DemoVerificationSeeder extends Seeder
{
    use SeedsDemoData;
    use WithoutModelEvents;

    public function run(): void
    {
        $orders = Order::query()
            ->where('order_number', 'like', 'DEMO-ORD-%')
            ->orderBy('id')
            ->get();

        if ($orders->isEmpty()) {
            return;
        }

        OrderVerificationAttempt::query()
            ->whereIn('order_id', $orders->pluck('id'))
            ->delete();

        $outcomes = [
            'verified',
            'callback_requested',
            'no_answer',
            'wrong_number',
            'customer_cancelled',
            'duplicate_order',
            'suspicious',
            'failed_verification',
        ];

        foreach ($orders as $index => $order) {
            $outcome = $outcomes[$index % count($outcomes)];

            OrderVerificationAttempt::create([
                'order_id' => $order->id,
                'staff_user_id' => $this->demoStaffId(),
                'outcome' => $outcome,
                'notes' => 'DEMO QA verification note for '.str_replace('_', ' ', $outcome).'. Customer confirmed test workflow details only.',
                'next_follow_up_at' => in_array($outcome, ['callback_requested', 'no_answer'], true) ? now()->addHours(6 + $index) : null,
                'created_at' => $order->created_at?->copy()->addHours(2) ?? now()->subDays(3),
                'updated_at' => $order->created_at?->copy()->addHours(2) ?? now()->subDays(3),
            ]);

            $order->forceFill([
                'verification_status' => $this->verificationStatusFor($outcome, (string) $order->status),
                'verified_at' => $outcome === 'verified' ? ($order->created_at?->copy()->addHours(3) ?? now()) : $order->verified_at,
                'verified_by' => $outcome === 'verified' ? $this->demoStaffId() : $order->verified_by,
            ])->save();
        }
    }

    protected function verificationStatusFor(string $outcome, string $orderStatus): string
    {
        if ($orderStatus === 'cancelled') {
            return 'cancelled';
        }

        return match ($outcome) {
            'verified' => 'verified',
            'callback_requested' => 'callback_requested',
            'no_answer' => 'no_answer',
            'wrong_number', 'duplicate_order', 'suspicious', 'failed_verification' => 'failed',
            'customer_cancelled' => 'cancelled',
            default => 'pending',
        };
    }
}
