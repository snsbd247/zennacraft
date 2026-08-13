<?php

namespace App\Modules\Communication\Models;

use App\Modules\Customer\Models\Customer;
use App\Modules\Order\Models\Order;
use App\Modules\Promotion\Models\Coupon;
use App\Modules\Recovery\Models\CheckoutRecovery;
use App\Modules\Performance\Services\CacheService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Throwable;

class CommunicationMessage extends Model
{
    public const STATUSES = [
        'pending',
        'queued',
        'sent',
        'delivered',
        'failed',
    ];

    public const CHANNELS = [
        'email',
        'sms',
        'whatsapp',
        'internal',
        'messenger',
    ];

    public const QUEUE_TIER_OTP = 'otp';

    public const QUEUE_TIER_TRANSACTIONAL = 'transactional';

    public const QUEUE_TIER_BULK = 'bulk';

    // Server-generated login codes only. Never staff/campaign-configurable —
    // 'otp_verification' is a currently-unused template kept classified here
    // by name in case it's ever wired up for the same purpose.
    protected const OTP_TEMPLATES = [
        'customer_otp',
        'otp_verification',
    ];

    // Single customer/order, event-triggered — one message per real
    // occurrence, never a fan-out to many recipients in one call.
    protected const TRANSACTIONAL_TEMPLATES = [
        'order_created',
        'order_pending',
        'order_confirmed',
        'order_processing',
        'order_shipped',
        'order_delivered',
        'order_cancelled',
        'order_returned',
        'review_request',
        'review_thank_you',
        'recovery_reminder',
        'abandoned_checkout',
        'vip_loyalty_message',
    ];

    /**
     * Which priority queue this message belongs on, derived from its own
     * `template` column — no separate schema column needed. Anything not
     * explicitly recognised (including a marketing campaign's free-text
     * template_key, which isn't constrained to a known set) defaults to
     * the slow `bulk` lane rather than risk it jumping ahead of real OTP
     * traffic. Known bulk-fanout call sites (campaign/segment/coupon
     * blasts) pass an explicit 'bulk' override at dispatch time instead of
     * relying on this inference — see CommunicationService::queueMessage().
     */
    public function queueTier(): string
    {
        if (in_array($this->template, self::OTP_TEMPLATES, true)) {
            return self::QUEUE_TIER_OTP;
        }

        if (
            in_array($this->template, self::TRANSACTIONAL_TEMPLATES, true)
            || str_starts_with((string) $this->template, 'verification_')
        ) {
            return self::QUEUE_TIER_TRANSACTIONAL;
        }

        return self::QUEUE_TIER_BULK;
    }

    protected $fillable = [
        'customer_id',
        'order_id',
        'recovery_id',
        'coupon_id',
        'channel',
        'recipient',
        'recipient_hash',
        'template',
        'subject',
        'body',
        'variables',
        'status',
        'queued_at',
        'sent_at',
        'failed_at',
        'provider_response',
        'error_message',
    ];

    protected $casts = [
        'variables' => 'array',
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
        'provider_response' => 'array',
    ];

    protected static function booted(): void
    {
        static::saved(fn (): null => static::flushCommunicationDashboardCache());
        static::deleted(fn (): null => static::flushCommunicationDashboardCache());
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function recovery(): BelongsTo
    {
        return $this->belongsTo(CheckoutRecovery::class, 'recovery_id');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(CommunicationLog::class);
    }

    protected static function flushCommunicationDashboardCache(): null
    {
        try {
            $cacheService = app(CacheService::class);
            $cacheService->invalidateCommunicationDashboard();
            $cacheService->invalidateMarketingCommandCenter();
        } catch (Throwable) {
            // Dashboard cache invalidation should never interrupt message writes.
        }

        return null;
    }
}
