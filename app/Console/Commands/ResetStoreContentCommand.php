<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wipes all demo/test CONTENT (products, orders, customers, marketing, finance
 * transactions, …) so the store can be filled with real data — while KEEPING
 * every piece of configuration: settings, theme + logo, staff/owner login,
 * roles & permissions, cities, courier providers, CMS pages, expense
 * categories, the accounts chart and marketing-segment definitions.
 *
 * The list below is an explicit allow-to-DELETE list: any table not named here
 * (settings, theme_settings, staff_users, media, courier_providers, cms_pages,
 * …) is left completely untouched. Tables are truncated with FK checks off so
 * auto-increment ids reset to 1 for a genuinely fresh start.
 */
class ResetStoreContentCommand extends Command
{
    protected $signature = 'studio:reset-content {--force : Skip the confirmation prompt}';

    protected $description = 'Delete all demo/test content, keeping configuration (settings, theme, staff, couriers, CMS, etc.)';

    /** Content tables to clear (children first is irrelevant — FK checks are disabled). */
    private array $contentTables = [
        // Orders & fulfilment
        'order_verification_attempts', 'order_risk_profiles', 'order_status_histories',
        'order_notes', 'order_items', 'orders',
        'shipment_tracking_events', 'shipments', 'courier_metrics',
        // Catalog
        'product_damage_items', 'product_damages', 'variant_inventory_logs', 'inventory_logs',
        'product_media', 'product_reviews', 'product_variants', 'products',
        'product_attribute_values', 'product_attributes', 'product_colors', 'product_sizes',
        'categories', 'brands',
        // Customers
        'customer_behavior_events', 'customer_blacklists', 'customer_communications', 'customer_otps',
        'customer_risk_profiles', 'customer_segments', 'customer_shadow_accounts',
        'marketing_segment_memberships', 'customers',
        // Offers / coupons / marketing content
        'coupon_usages', 'coupon_targets', 'coupons', 'offers', 'combo_items', 'combos',
        'storefront_sliders', 'landing_pages', 'marketing_campaign_logs', 'marketing_campaigns',
        // Messaging / automation / fraud / recovery / tracking events
        'communication_logs', 'communication_messages',
        'automation_runs', 'automation_actions', 'automation_workflows',
        'fraud_events', 'checkout_recoveries', 'facebook_events', 'audit_logs',
        // Purchasing
        'purchase_items', 'purchases', 'supplier_payments', 'suppliers',
        // Finance transactions (chart of accounts is kept)
        'account_transactions', 'fund_transfers', 'bill_statements', 'expenses', 'ad_spends', 'employees',
        // Queue backlog
        'failed_jobs', 'jobs', 'job_batches',
    ];

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('This deletes ALL demo/test content on the current database (config is kept). Continue?')) {
            $this->warn('Aborted.');

            return self::FAILURE;
        }

        $driver = DB::getDriverName();
        $this->toggleForeignKeys($driver, false);

        $cleared = [];
        try {
            foreach ($this->contentTables as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }
                $count = DB::table($table)->count();
                DB::table($table)->truncate();
                if ($count > 0) {
                    $cleared[$table] = $count;
                }
            }
        } finally {
            $this->toggleForeignKeys($driver, true);
        }

        if ($cleared === []) {
            $this->info('Nothing to clear — content tables were already empty.');
        } else {
            $this->table(['Table', 'Rows removed'], collect($cleared)->map(fn ($n, $t) => [$t, $n])->values()->all());
            $this->info('Cleared '.array_sum($cleared).' rows across '.count($cleared).' tables. Configuration kept intact.');
        }

        return self::SUCCESS;
    }

    private function toggleForeignKeys(string $driver, bool $on): void
    {
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('SET FOREIGN_KEY_CHECKS='.($on ? '1' : '0'));
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = '.($on ? 'ON' : 'OFF'));
        }
    }
}
