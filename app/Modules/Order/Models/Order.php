<?php

namespace App\Modules\Order\Models;

use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Analytics\Concerns\InvalidatesAnalyticsCache;
use App\Modules\Communication\Models\CommunicationMessage;
use App\Modules\Courier\Models\Shipment;
use App\Modules\Customer\Models\CustomerCommunication;
use App\Modules\Customer\Models\Customer;
use App\Modules\Fraud\Models\FraudEvent;
use App\Modules\LandingPage\Models\LandingPage;
use App\Modules\Promotion\Models\Coupon;
use App\Modules\Promotion\Models\CouponUsage;
use App\Modules\Review\Models\ProductReview;
use App\Modules\RTO\Models\OrderRiskProfile;
use App\Modules\Tracking\Models\ShipmentTrackingEvent;
use App\Modules\Verification\Models\OrderVerificationAttempt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use InvalidatesAnalyticsCache;

    // Spec §3.8. "custom" is set by admin-created orders (Phase 3C); "whatsapp"
    // has no write path yet either — both are valid, reserved values.
    public const SOURCES = ['website', 'landing', 'custom', 'whatsapp', 'pos'];

    // Spec §3.6.
    public const RTO_REASONS = ['didnt_take', 'phone_off', 'wrong_address', 'other'];

    // Phase 3E: manual, staff-applied discount — a flat ৳ amount or a
    // percentage of subtotal, independent of any coupon.
    public const MANUAL_DISCOUNT_TYPES = ['flat', 'percent'];

    // Phase 3E: line-item/discount editing is only safe while nothing
    // physical has moved yet.
    public const EDITABLE_STATUSES = ['pending', 'confirmed', 'processing'];

    protected $fillable = [
        'customer_id',
        'coupon_id',
        'order_number',
        'coupon_code',
        'coupon_discount_amount',
        'coupon_free_shipping',
        'manual_discount_type',
        'manual_discount_value',
        'manual_discount_amount',
        'manual_discount_reason',
        'customer_name',
        'customer_phone',
        'customer_email',
        'address',
        'district',
        'subtotal',
        'delivery_fee',
        'delivery_zone',
        'payment_method',
        'total',
        'paid_amount',
        'paid_by',
        'payment_gateway_reference',
        'payment_transaction_id',
        'payment_status',
        'product_cost_total',
        'courier_cost_total',
        'gross_profit',
        'status',
        'source',
        'source_landing_page_id',
        'exchanged_from_order_id',
        'rto_reason',
        'verification_status',
        'verified_at',
        'verified_by',
        'inventory_reserved_at',
        'inventory_released_at',
        'notes',
        'risk_hold_status',
        'risk_hold_reason',
        'risk_hold_at',
        'risk_hold_released_at',
        'risk_hold_released_by',
        'risk_hold_release_note',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'total' => 'decimal:2',
        'coupon_discount_amount' => 'decimal:2',
        'coupon_free_shipping' => 'boolean',
        'manual_discount_value' => 'decimal:2',
        'manual_discount_amount' => 'decimal:2',
        'product_cost_total' => 'decimal:2',
        'courier_cost_total' => 'decimal:2',
        'gross_profit' => 'decimal:2',
        'verified_at' => 'datetime',
        'inventory_reserved_at' => 'datetime',
        'inventory_released_at' => 'datetime',
        'risk_hold_at' => 'datetime',
        'risk_hold_released_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function couponUsage(): HasOne
    {
        return $this->hasOne(CouponUsage::class);
    }

    public function fraudEvents(): HasMany
    {
        return $this->hasMany(FraudEvent::class);
    }

    public function customerCommunications(): HasMany
    {
        return $this->hasMany(CustomerCommunication::class)->latest();
    }

    public function communicationMessages(): HasMany
    {
        return $this->hasMany(CommunicationMessage::class)->latest();
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->latest();
    }

    public function verificationAttempts(): HasMany
    {
        return $this->hasMany(OrderVerificationAttempt::class)->latest();
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'verified_by');
    }

    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class);
    }

    public function trackingEvents(): HasMany
    {
        return $this->hasMany(ShipmentTrackingEvent::class)->orderBy('event_time')->orderBy('id');
    }

    public function riskProfile(): HasOne
    {
        return $this->hasOne(OrderRiskProfile::class);
    }

    public function productReviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function riskHoldReleasedBy(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'risk_hold_released_by');
    }

    public function sourceLandingPage(): BelongsTo
    {
        return $this->belongsTo(LandingPage::class, 'source_landing_page_id');
    }

    // Named orderNotes(), not notes() — `notes` is already a plain text
    // column on this model (customer-provided checkout instructions);
    // a same-named relationship method would shadow it via Eloquent's
    // magic __get, silently breaking every existing read of that column.
    public function orderNotes(): HasMany
    {
        return $this->hasMany(OrderNote::class)->latest();
    }

    // The original order this one was created to replace (Studio "Add
    // Exchange Order"), and the inverse — any exchange orders spawned
    // from this one.
    public function exchangedFrom(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'exchanged_from_order_id');
    }

    public function exchangeOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'exchanged_from_order_id');
    }
}
