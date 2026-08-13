<?php

namespace Database\Seeders;

use App\Modules\Communication\Models\CommunicationMessage;
use App\Modules\Product\Models\Product;
use App\Modules\Recovery\Models\CheckoutRecovery;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DemoRecoverySeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $products = Product::query()
            ->where('sku', 'like', 'ZC-DEMO-%')
            ->with('storefrontVariants')
            ->orderBy('id')
            ->get();

        if ($products->isEmpty()) {
            return;
        }

        $oldRecoveryIds = CheckoutRecovery::query()
            ->where('address', 'like', 'DEMO-QA Recovery%')
            ->pluck('id');

        CommunicationMessage::query()
            ->whereIn('recovery_id', $oldRecoveryIds)
            ->orWhere('template', 'like', 'demo_recovery_%')
            ->delete();

        CheckoutRecovery::query()
            ->whereIn('id', $oldRecoveryIds)
            ->delete();

        $statuses = ['open', 'contacted', 'callback_pending', 'no_answer', 'interested', 'recovered', 'lost', 'closed'];
        $stages = ['Cart', 'Phone Entered', 'Address Entered', 'Checkout Started', 'COD Confirm Step', 'Left Before Submit'];

        foreach (range(1, 14) as $index) {
            $product = $products[($index - 1) % $products->count()];
            $variant = $product->storefrontVariants->values()->get(($index - 1) % max(1, $product->storefrontVariants->count()));
            $status = $statuses[($index - 1) % count($statuses)];
            $phone = $index % 5 === 0 ? null : '01000009'.str_pad((string) $index, 2, '0', STR_PAD_LEFT);

            $recovery = CheckoutRecovery::create([
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'customer_name' => $phone ? 'Demo Recovery Lead '.$index : null,
                'customer_phone' => $phone,
                'customer_email' => $phone ? 'demo.recovery.'.$index.'@example.test' : null,
                'address' => 'DEMO-QA Recovery | Stage: '.$stages[$index % count($stages)].' | Priority: '.$this->priority($index),
                'quantity' => 1 + ($index % 3),
                'status' => $status,
                'created_at' => now()->subHours(4 + $index),
                'updated_at' => now()->subHours($index % 4),
            ]);

            if ($phone) {
                $this->message($recovery, $phone, $index, $status);
            }
        }
    }

    protected function priority(int $index): string
    {
        return match (true) {
            $index % 3 === 0 => 'Hot',
            $index % 2 === 0 => 'Warm',
            default => 'Cold',
        };
    }

    protected function message(CheckoutRecovery $recovery, string $phone, int $index, string $status): void
    {
        CommunicationMessage::create([
            'recovery_id' => $recovery->id,
            'channel' => $index % 2 === 0 ? 'whatsapp' : 'sms',
            'recipient' => $phone,
            'recipient_hash' => hash('sha256', $phone),
            'template' => 'demo_recovery_'.$status,
            'subject' => 'DEMO QA recovery follow-up',
            'body' => 'Demo QA queued recovery message only. No external sending is performed.',
            'variables' => [
                'recovery_id' => $recovery->id,
                'demo' => true,
                'status' => $status,
            ],
            'status' => in_array($status, ['recovered', 'contacted'], true) ? 'sent' : 'queued',
            'queued_at' => now()->subHours(2 + $index),
            'sent_at' => in_array($status, ['recovered', 'contacted'], true) ? now()->subHours(1 + $index) : null,
        ]);
    }
}
