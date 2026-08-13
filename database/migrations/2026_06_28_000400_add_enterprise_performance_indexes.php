<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex('orders', ['verification_status', 'created_at'], 'orders_verification_created_perf_idx');
        $this->addIndex('orders', ['risk_hold_status', 'created_at'], 'orders_risk_hold_created_perf_idx');
        $this->addIndex('orders', ['coupon_id', 'created_at'], 'orders_coupon_created_perf_idx');

        $this->addIndex('order_items', ['product_id', 'created_at'], 'order_items_product_created_perf_idx');
        $this->addIndex('order_items', ['variant_id', 'created_at'], 'order_items_variant_created_perf_idx');

        $this->addIndex('customers', ['last_order_at', 'created_at'], 'customers_last_order_created_perf_idx');
        $this->addIndex('customers', ['delivered_orders', 'last_order_at'], 'customers_delivered_last_order_perf_idx');

        $this->addIndex('products', ['category_id', 'status', 'created_at'], 'products_category_status_created_perf_idx');
        $this->addIndex('product_reviews', ['product_id', 'status', 'rating'], 'reviews_product_status_rating_perf_idx');
        $this->addIndex('product_reviews', ['customer_id', 'created_at'], 'reviews_customer_created_perf_idx');
        $this->addIndex('product_reviews', ['order_id', 'product_id', 'product_variant_id'], 'reviews_order_product_variant_perf_idx');

        $this->addIndex('customer_behavior_events', ['event_type', 'occurred_at'], 'behavior_event_type_occurred_perf_idx');
        $this->addIndex('customer_behavior_events', ['product_id', 'event_type', 'occurred_at'], 'behavior_product_event_occurred_perf_idx');
        $this->addIndex('customer_behavior_events', ['customer_id', 'occurred_at'], 'behavior_customer_occurred_perf_idx');
        $this->addIndex('customer_behavior_events', ['source', 'event_type', 'occurred_at'], 'behavior_source_event_occurred_perf_idx');

        $this->addIndex('checkout_recoveries', ['status', 'updated_at'], 'recoveries_status_updated_perf_idx');
        $this->addIndex('checkout_recoveries', ['product_id', 'status', 'created_at'], 'recoveries_product_status_created_perf_idx');

        $this->addIndex('order_verification_attempts', ['outcome', 'created_at'], 'verification_outcome_created_perf_idx');
        $this->addIndex('order_verification_attempts', ['order_id', 'created_at'], 'verification_order_created_perf_idx');

        $this->addIndex('shipments', ['status', 'created_at'], 'shipments_status_created_perf_idx');
        $this->addIndex('shipments', ['courier_provider_id', 'status', 'created_at'], 'shipments_courier_status_created_perf_idx');
        $this->addIndex('shipment_tracking_events', ['shipment_id', 'event_time'], 'tracking_shipment_event_time_perf_idx');

        $this->addIndex('communication_messages', ['status', 'queued_at'], 'messages_status_queued_perf_idx');
        $this->addIndex('communication_messages', ['customer_id', 'created_at'], 'messages_customer_created_perf_idx');
        $this->addIndex('communication_messages', ['order_id', 'created_at'], 'messages_order_created_perf_idx');

        $this->addIndex('automation_runs', ['status', 'created_at'], 'automation_runs_status_created_perf_idx');
        $this->addIndex('automation_runs', ['trigger_key', 'status', 'created_at'], 'automation_runs_trigger_status_perf_idx');
        $this->addIndex('automation_runs', ['subject_type', 'subject_id', 'created_at'], 'automation_runs_subject_created_perf_idx');
        $this->addIndex('automation_actions', ['status', 'executed_at'], 'automation_actions_status_executed_perf_idx');

        $this->addIndex('marketing_campaigns', ['status', 'starts_at'], 'campaigns_status_starts_perf_idx');
        $this->addIndex('marketing_campaigns', ['campaign_type', 'status'], 'campaigns_type_status_perf_idx');
        $this->addIndex('marketing_campaign_logs', ['marketing_campaign_id', 'created_at'], 'campaign_logs_campaign_created_perf_idx');

        $this->addIndex('coupon_usages', ['coupon_id', 'used_at'], 'coupon_usages_coupon_used_perf_idx');
        $this->addIndex('coupon_usages', ['customer_id', 'used_at'], 'coupon_usages_customer_used_perf_idx');
        $this->addIndex('ad_spends', ['platform', 'spend_date'], 'ad_spends_platform_date_perf_idx');
        $this->addIndex('expenses', ['expense_category_id', 'expense_date'], 'expenses_category_date_perf_idx');
    }

    public function down(): void
    {
        foreach ([
            ['orders', 'orders_verification_created_perf_idx'],
            ['orders', 'orders_risk_hold_created_perf_idx'],
            ['orders', 'orders_coupon_created_perf_idx'],
            ['order_items', 'order_items_product_created_perf_idx'],
            ['order_items', 'order_items_variant_created_perf_idx'],
            ['customers', 'customers_last_order_created_perf_idx'],
            ['customers', 'customers_delivered_last_order_perf_idx'],
            ['products', 'products_category_status_created_perf_idx'],
            ['product_reviews', 'reviews_product_status_rating_perf_idx'],
            ['product_reviews', 'reviews_customer_created_perf_idx'],
            ['product_reviews', 'reviews_order_product_variant_perf_idx'],
            ['customer_behavior_events', 'behavior_event_type_occurred_perf_idx'],
            ['customer_behavior_events', 'behavior_product_event_occurred_perf_idx'],
            ['customer_behavior_events', 'behavior_customer_occurred_perf_idx'],
            ['customer_behavior_events', 'behavior_source_event_occurred_perf_idx'],
            ['checkout_recoveries', 'recoveries_status_updated_perf_idx'],
            ['checkout_recoveries', 'recoveries_product_status_created_perf_idx'],
            ['order_verification_attempts', 'verification_outcome_created_perf_idx'],
            ['order_verification_attempts', 'verification_order_created_perf_idx'],
            ['shipments', 'shipments_status_created_perf_idx'],
            ['shipments', 'shipments_courier_status_created_perf_idx'],
            ['shipment_tracking_events', 'tracking_shipment_event_time_perf_idx'],
            ['communication_messages', 'messages_status_queued_perf_idx'],
            ['communication_messages', 'messages_customer_created_perf_idx'],
            ['communication_messages', 'messages_order_created_perf_idx'],
            ['automation_runs', 'automation_runs_status_created_perf_idx'],
            ['automation_runs', 'automation_runs_trigger_status_perf_idx'],
            ['automation_runs', 'automation_runs_subject_created_perf_idx'],
            ['automation_actions', 'automation_actions_status_executed_perf_idx'],
            ['marketing_campaigns', 'campaigns_status_starts_perf_idx'],
            ['marketing_campaigns', 'campaigns_type_status_perf_idx'],
            ['marketing_campaign_logs', 'campaign_logs_campaign_created_perf_idx'],
            ['coupon_usages', 'coupon_usages_coupon_used_perf_idx'],
            ['coupon_usages', 'coupon_usages_customer_used_perf_idx'],
            ['ad_spends', 'ad_spends_platform_date_perf_idx'],
            ['expenses', 'expenses_category_date_perf_idx'],
        ] as [$table, $name]) {
            $this->dropIndex($table, $name);
        }
    }

    private function addIndex(string $table, array $columns, string $name): void
    {
        if (! Schema::hasTable($table) || $this->hasIndex($table, $name)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return;
            }
        }

        Schema::table($table, function (Blueprint $table) use ($columns, $name): void {
            $table->index($columns, $name);
        });
    }

    private function dropIndex(string $table, string $name): void
    {
        if (! Schema::hasTable($table) || ! $this->hasIndex($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($name): void {
            $table->dropIndex($name);
        });
    }

    private function hasIndex(string $table, string $name): bool
    {
        try {
            return collect(Schema::getIndexes($table))
                ->contains(fn (array $index): bool => ($index['name'] ?? null) === $name);
        } catch (Throwable) {
            return false;
        }
    }
};
