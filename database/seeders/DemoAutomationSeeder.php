<?php

namespace Database\Seeders;

use App\Modules\Automation\Models\AutomationAction;
use App\Modules\Automation\Models\AutomationRun;
use App\Modules\Automation\Models\AutomationWorkflow;
use App\Modules\Communication\Models\CommunicationMessage;
use App\Modules\Order\Models\Order;
use App\Modules\Recovery\Models\CheckoutRecovery;
use Database\Seeders\Concerns\SeedsDemoData;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DemoAutomationSeeder extends Seeder
{
    use SeedsDemoData;
    use WithoutModelEvents;

    public function run(): void
    {
        $workflows = $this->workflows();
        $this->runs($workflows);
    }

    protected function workflows(): array
    {
        $definitions = [
            [
                'name' => 'Demo Order Created SMS',
                'slug' => 'demo-order-created-sms',
                'trigger_key' => 'order.created',
                'status' => 'active',
                'conditions' => [['key' => 'always', 'value' => true]],
                'actions' => [['key' => 'queue_communication', 'channel' => 'sms', 'template' => 'demo_order_created_sms']],
            ],
            [
                'name' => 'Demo Abandoned Cart Recovery',
                'slug' => 'demo-abandoned-cart-recovery',
                'trigger_key' => 'checkout.abandoned',
                'status' => 'active',
                'conditions' => [['key' => 'customer_not_blacklisted', 'value' => true]],
                'actions' => [['key' => 'queue_communication', 'channel' => 'whatsapp', 'template' => 'demo_abandoned_cart_recovery']],
            ],
            [
                'name' => 'Demo Review Request',
                'slug' => 'demo-review-request',
                'trigger_key' => 'order.status_changed',
                'status' => 'active',
                'conditions' => [['key' => 'order_status_is', 'value' => 'delivered']],
                'actions' => [['key' => 'queue_communication', 'channel' => 'sms', 'template' => 'demo_review_request']],
            ],
            [
                'name' => 'Demo VIP Thank You',
                'slug' => 'demo-vip-thank-you-automation',
                'trigger_key' => 'customer.segment_entered',
                'status' => 'paused',
                'conditions' => [['key' => 'customer_in_segment', 'value' => 'VIP_CUSTOMER']],
                'actions' => [['key' => 'queue_communication', 'channel' => 'internal', 'template' => 'demo_vip_thank_you']],
            ],
            [
                'name' => 'Demo Courier Tracking Message',
                'slug' => 'demo-courier-tracking-message',
                'trigger_key' => 'order.status_changed',
                'status' => 'active',
                'conditions' => [['key' => 'order_status_is', 'value' => 'shipped']],
                'actions' => [['key' => 'queue_communication', 'channel' => 'sms', 'template' => 'demo_courier_tracking']],
            ],
        ];

        $workflows = [];

        foreach ($definitions as $definition) {
            $workflows[] = AutomationWorkflow::updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'name' => $definition['name'],
                    'description' => 'DEMO QA automation workflow. It queues records only and does not send external messages.',
                    'trigger_key' => $definition['trigger_key'],
                    'status' => $definition['status'],
                    'conditions_json' => $definition['conditions'],
                    'actions_json' => $definition['actions'],
                    'last_run_at' => now()->subHours(3),
                    'created_by' => $this->demoStaffId(),
                ]
            );
        }

        return $workflows;
    }

    protected function runs(array $workflows): void
    {
        $workflowIds = collect($workflows)->pluck('id');
        $runIds = AutomationRun::query()
            ->whereIn('automation_workflow_id', $workflowIds)
            ->pluck('id');

        AutomationAction::query()->whereIn('automation_run_id', $runIds)->delete();
        AutomationRun::query()->whereIn('id', $runIds)->delete();
        CommunicationMessage::query()->where('template', 'like', 'demo_automation_%')->delete();

        $orders = Order::query()->where('order_number', 'like', 'DEMO-ORD-%')->take(8)->get();
        $recoveries = CheckoutRecovery::query()->where('address', 'like', 'DEMO-QA Recovery%')->take(4)->get();

        foreach ($workflows as $index => $workflow) {
            $subject = str_contains((string) $workflow->trigger_key, 'checkout')
                ? $recoveries->get($index % max(1, $recoveries->count()))
                : $orders->get($index % max(1, $orders->count()));

            if (! $subject) {
                continue;
            }

            $message = CommunicationMessage::create([
                'customer_id' => $subject instanceof Order ? $subject->customer_id : null,
                'order_id' => $subject instanceof Order ? $subject->id : null,
                'recovery_id' => $subject instanceof CheckoutRecovery ? $subject->id : null,
                'channel' => 'internal',
                'recipient' => null,
                'recipient_hash' => null,
                'template' => 'demo_automation_'.$workflow->slug,
                'subject' => 'DEMO QA automation queue',
                'body' => 'Demo automation queue record only. No external sending is performed.',
                'variables' => [
                    'workflow_id' => $workflow->id,
                    'demo' => true,
                ],
                'status' => $index % 4 === 0 ? 'failed' : 'queued',
                'queued_at' => now()->subHours(6 - min($index, 5)),
                'failed_at' => $index % 4 === 0 ? now()->subHours(2) : null,
                'error_message' => $index % 4 === 0 ? 'DEMO QA simulated provider disabled state.' : null,
            ]);

            $run = AutomationRun::create([
                'automation_workflow_id' => $workflow->id,
                'subject_type' => $subject::class,
                'subject_id' => $subject->id,
                'status' => $index % 4 === 0 ? 'failed' : 'completed',
                'trigger_key' => $workflow->trigger_key,
                'result_summary' => $index % 4 === 0 ? 'DEMO QA failed run for error-state testing.' : 'DEMO QA completed run.',
                'metadata' => [
                    'demo' => true,
                    'message_id' => $message->id,
                ],
                'started_at' => now()->subHours(8 - min($index, 6)),
                'finished_at' => now()->subHours(7 - min($index, 6)),
            ]);

            AutomationAction::create([
                'automation_run_id' => $run->id,
                'action_key' => 'queue_communication',
                'status' => $run->status === 'failed' ? 'failed' : 'completed',
                'result_summary' => $run->status === 'failed'
                    ? 'DEMO QA message was not sent externally.'
                    : 'DEMO QA communication record queued.',
                'metadata' => [
                    'demo' => true,
                    'message_id' => $message->id,
                ],
                'executed_at' => now()->subHours(6 - min($index, 5)),
            ]);
        }
    }
}
