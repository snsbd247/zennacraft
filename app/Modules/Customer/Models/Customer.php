<?php

namespace App\Modules\Customer\Models;

use App\Modules\Analytics\Concerns\InvalidatesAnalyticsCache;
use App\Modules\Analytics\Models\CustomerBehaviorEvent;
use App\Modules\Automation\Models\AutomationRun;
use App\Modules\Communication\Models\CommunicationMessage;
use App\Modules\Order\Models\Order;
use App\Modules\Promotion\Models\CouponUsage;
use App\Modules\Fraud\Models\CustomerBlacklist;
use App\Modules\Fraud\Models\FraudEvent;
use App\Modules\Automation\Services\AutomationEngineService;
use App\Modules\Marketing\Models\MarketingSegment;
use App\Modules\Marketing\Models\MarketingSegmentMembership;
use App\Modules\Marketing\Services\MarketingSegmentEngineService;
use App\Modules\RTO\Models\CustomerRiskProfile;
use App\Modules\Review\Models\ProductReview;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Throwable;

class Customer extends Authenticatable
{
    use HasApiTokens;
    use InvalidatesAnalyticsCache;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
        'total_orders',
        'total_spent',
        'delivered_orders',
        'cancelled_orders',
        'returned_orders',
        'first_order_at',
        'last_order_at',
        'cod_score',
    ];

    protected $casts = [
        'total_orders' => 'integer',
        'total_spent' => 'decimal:2',
        'delivered_orders' => 'integer',
        'cancelled_orders' => 'integer',
        'returned_orders' => 'integer',
        'first_order_at' => 'datetime',
        'last_order_at' => 'datetime',
        'cod_score' => 'integer',
    ];

    protected static function booted(): void
    {
        static::created(function (Customer $customer): void {
            try {
                app(AutomationEngineService::class)->handleTrigger('customer.created', $customer, [
                    'customer_id' => $customer->id,
                ]);
            } catch (Throwable) {
                // Automation triggers must not interrupt customer creation.
            }
        });

        static::saved(function (Customer $customer): void {
            try {
                app(MarketingSegmentEngineService::class)->evaluateCustomer($customer, 'customer:saved');
            } catch (Throwable) {
                // Marketing segment evaluation must not interrupt customer writes.
            }
        });
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function communications(): HasMany
    {
        return $this->hasMany(CustomerCommunication::class);
    }

    public function communicationMessages(): HasMany
    {
        return $this->hasMany(CommunicationMessage::class)->latest();
    }

    public function behaviorEvents(): HasMany
    {
        return $this->hasMany(CustomerBehaviorEvent::class)->latest('occurred_at');
    }

    public function automationRuns(): HasMany
    {
        $subjectTypes = array_values(array_unique([self::class, $this->getMorphClass(), 'customer']));

        return $this->hasMany(AutomationRun::class, 'subject_id')
            ->whereIn('subject_type', $subjectTypes)
            ->latest();
    }

    public function couponUsages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function fraudEvents(): HasMany
    {
        return $this->hasMany(FraudEvent::class);
    }

    public function blacklistEntries(): HasMany
    {
        return $this->hasMany(CustomerBlacklist::class);
    }

    public function shadowAccount(): HasOne
    {
        return $this->hasOne(CustomerShadowAccount::class);
    }

    public function riskProfile(): HasOne
    {
        return $this->hasOne(CustomerRiskProfile::class);
    }

    public function segment(): HasOne
    {
        return $this->hasOne(CustomerSegment::class);
    }

    public function marketingSegmentMemberships(): HasMany
    {
        return $this->hasMany(MarketingSegmentMembership::class);
    }

    public function marketingSegments(): BelongsToMany
    {
        return $this->belongsToMany(MarketingSegment::class, 'marketing_segment_memberships')
            ->withPivot(['joined_at', 'last_evaluated_at'])
            ->withTimestamps();
    }

    public function productReviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }
}
