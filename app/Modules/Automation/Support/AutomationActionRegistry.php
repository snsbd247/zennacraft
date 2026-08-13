<?php

namespace App\Modules\Automation\Support;

class AutomationActionRegistry
{
    public static function all(): array
    {
        return [
            'queue_communication' => [
                'key' => 'queue_communication',
                'label' => 'Queue Communication',
                'description' => 'Creates and queues a communication message record without dispatching external sending.',
            ],
            'add_to_segment' => [
                'key' => 'add_to_segment',
                'label' => 'Add To Segment',
                'description' => 'Adds the customer to a marketing segment membership.',
            ],
            'remove_from_segment' => [
                'key' => 'remove_from_segment',
                'label' => 'Remove From Segment',
                'description' => 'Removes the customer from a marketing segment membership.',
            ],
            'create_internal_note' => [
                'key' => 'create_internal_note',
                'label' => 'Create Internal Note',
                'description' => 'Logs an internal automation action record.',
            ],
            'create_followup_task' => [
                'key' => 'create_followup_task',
                'label' => 'Create Follow-up Task',
                'description' => 'Logs a follow-up task action for future workflow expansion.',
            ],
            'mark_for_review' => [
                'key' => 'mark_for_review',
                'label' => 'Mark For Review',
                'description' => 'Logs a manual review action for staff visibility.',
            ],
            'launch_campaign' => [
                'key' => 'launch_campaign',
                'label' => 'Launch Campaign',
                'description' => 'Activates an existing marketing campaign safely.',
            ],
            'queue_campaign' => [
                'key' => 'queue_campaign',
                'label' => 'Queue Campaign',
                'description' => 'Queues messages for an existing marketing campaign without external sending.',
            ],
            'pause_campaign' => [
                'key' => 'pause_campaign',
                'label' => 'Pause Campaign',
                'description' => 'Pauses an existing marketing campaign.',
            ],
        ];
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }
}
