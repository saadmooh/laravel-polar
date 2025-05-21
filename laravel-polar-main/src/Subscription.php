<?php

namespace Danestves\LaravelPolar;

use Danestves\LaravelPolar\Data\Subscriptions\SubscriptionCancelData;
use Danestves\LaravelPolar\Data\Subscriptions\SubscriptionUpdateProductData;
use Danestves\LaravelPolar\Enums\ProrationBehavior;
use Danestves\LaravelPolar\Enums\SubscriptionStatus;
use Danestves\LaravelPolar\Exceptions\PolarApiError;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $billable_type
 * @property int $billable_id
 * @property string $type
 * @property string $polar_id
 * @property SubscriptionStatus $status
 * @property string $product_id
 * @property string $price_id
 * @property \Carbon\CarbonInterface|null $current_period_end
 * @property \Carbon\CarbonInterface|null $trial_ends_at
 * @property \Carbon\CarbonInterface|null $ends_at
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property \Danestves\LaravelPolar\Billable $billable
 *
 * @mixin \Eloquent
 */
class Subscription extends Model // @phpstan-ignore-line propertyTag.trait - Billable is used in the user final code
{
    /** @use HasFactory<\Danestves\LaravelPolar\Database\Factories\SubscriptionFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'polar_subscriptions';

    /**
    * The attributes that are not mass assignable.
    *
    * @var array<string>|bool
    */
    protected $guarded = [];

    /**
     * Get the billable model related to the subscription.
     *
     * @return MorphTo<Model, covariant $this>
     */
    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Determine if the subscription is active, on trial, past due, or within its grace period.
     */
    public function valid(): bool
    {
        return $this->active() || $this->onTrial() || $this->pastDue() || $this->onGracePeriod();
    }

    /**
     * Determine if the subscription is incomplete.
     */
    public function incomplete(): bool
    {
        return $this->status === 'incomplete';
    }

    /**
     * Filter query by incomplete.
     *
     * @param  Builder<Subscription>  $query
     */
    public function scopeIncomplete(Builder $query): void
    {
        $query->where('status', 'incomplete');
    }

    /**
     * Determine if the subscription is incomplete expired.
     */
    public function incompleteExpired(): bool
    {
        return $this->status === "incomplete_expired";
    }

    /**
     * Filter query by incomplete expired.
     *
     * @param  Builder<Subscription>  $query
     */
    public function scopeIncompleteExpired(Builder $query): void
    {
        $query->where('status', "incomplete_expired");
    }

    /**
     * Determine if the subscription is trialing.
     */
    public function onTrial(): bool
    {
        return $this->status === "trialing";
    }

    /**
     * Filter query by on trial.
     *
     * @param  Builder<Subscription>  $query
     */
    public function scopeOnTrial(Builder $query): void
    {
        $query->where('status', "trialing");
    }

    /**
     * Determine if the subscription's trial has expired.
     */
    public function hasExpiredTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }

    /**
     * Check if the subscription is active.
     */
    public function active(): bool
    {
        return $this->status === "active";
    }
    
    
    public function paused(): bool
    {
        
        return $this->ends_at === null ? false : true;
    }

    /**
     * Filter query by active.
     *
     * @param  Builder<Subscription>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', "active");
    }

    /**
     * Check if the subscription is past due.
     */
    public function pastDue(): bool
    {
        return $this->status ==="past_due";
    }

    /**
     * Filter query by past due.
     *
     * @param  Builder<Subscription>  $query
     */
    public function scopePastDue(Builder $query): void
    {
        $query->where('status', "past_due");
    }

    /**
     * Check if the subscription is unpaid.
     */
    public function unpaid(): bool
    {
        return $this->status === "unpaid";
    }

    /**
     * Filter query by unpaid.
     *
     * @param  Builder<Subscription>  $query
     */
    public function scopeUnpaid(Builder $query): void
    {
        $query->where('status',"unpaid");
    }

    /**
     * Check if the subscription is cancelled.
     */
    public function cancelled(): bool
    {
        return $this->status === "canceled";
    }

    /**
     * Filter query by cancelled.
     *
     * @param  Builder<Subscription>  $query
     */
    public function scopeCancelled(Builder $query): void
    {
        $query->where('status', "canceled");
    }

    /**
     * Determine if the subscription is within its grace period after cancellation.
     */
    public function onGracePeriod(): bool
    {
        return $this->cancelled() && $this->ends_at?->isFuture();
    }

    /**
     * Determine if the subscription is on a specific product.
     */
    public function hasProduct(string $productId): bool
    {
        return $this->product_id === $productId;
    }

    /**
     * Determine if the subscription is on a specific price.
     */
    public function hasPrice(string $priceId): bool
    {
        return $this->price_id === $priceId;
    }

    /**
     * Swap the subscription to a new product.
     */
    public function swap(string $productId, ?ProrationBehavior $prorationBehavior = ProrationBehavior::Prorate): self
    {
   //  dd($productId);   
        $response = LaravelPolar::updateSubscription(
            subscriptionId: $this->polar_id,
            request: SubscriptionUpdateProductData::from([
                'productId' => $productId,
                'prorationBehavior' => $prorationBehavior,
            ]),
        );
       //   dd($response);
        $this->sync((array) $response);

        return $this;
    }

    /**
     * Swap the subscription to a new product plan and invoice immediately.
     */
    public function swapAndInvoice(string $productId): self
    {
        
        return $this->swap($productId, ProrationBehavior::Invoice);
    }

    /**
     * Cancel the subscription.
     */
    public function cancel(): self
    {
        $response = LaravelPolar::updateSubscription(
            subscriptionId: $this->polar_id,
            request: SubscriptionCancelData::from(['cancelAtPeriodEnd' => true]),
        );

        $this->sync((array) $response);

        return $this;
    }

    /**
     * Resume the subscription.
     */
    public function resume(): self
    {
        if ($this->status === SubscriptionStatus::IncompleteExpired) {
            throw new PolarApiError('Subscription is incomplete and expired.');
        }

        $response = LaravelPolar::updateSubscription(
            subscriptionId: $this->polar_id,
            request: SubscriptionCancelData::from(['cancelAtPeriodEnd' => false]),
        );

        $this->sync((array) $response);

        return $this;
    }

    /**
     * Sync the subscription with the given attributes.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function sync(array $attributes): self
    {
        $this->update([
            'status' => $attributes['status'],
            'product_id' => $attributes['productId'] ?? $attributes['product_id'],
            'price_id' => $attributes['priceId']  ?? $attributes['price_id'],
            'current_period_end' => isset($attributes['current_period_end']) ? Carbon::make($attributes['current_period_end']) : null,
            'ends_at' => isset($attributes['ends_at']) ? Carbon::make($attributes['ends_at']) : null,
        ]);

        return $this;
    }

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'current_period_end' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
