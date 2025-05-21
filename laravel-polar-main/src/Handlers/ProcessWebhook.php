<?php

namespace Danestves\LaravelPolar\Handlers;

use Carbon\Carbon;
use Danestves\LaravelPolar\Enums\OrderStatus;
use Danestves\LaravelPolar\Events\BenefitGrantCreated;
use Danestves\LaravelPolar\Events\BenefitGrantRevoked;
use Danestves\LaravelPolar\Events\BenefitGrantUpdated;
use Danestves\LaravelPolar\Events\OrderCreated;
use Danestves\LaravelPolar\Events\OrderUpdated;
use Danestves\LaravelPolar\Events\SubscriptionActive;
use Danestves\LaravelPolar\Events\SubscriptionCanceled;
use Danestves\LaravelPolar\Events\SubscriptionCreated;
use Danestves\LaravelPolar\Events\SubscriptionRevoked;
use Danestves\LaravelPolar\Events\SubscriptionUpdated;
use Danestves\LaravelPolar\Events\WebhookHandled;
use Danestves\LaravelPolar\Events\WebhookReceived;
use Danestves\LaravelPolar\Exceptions\InvalidMetadataPayload;
use Danestves\LaravelPolar\LaravelPolar;
use Danestves\LaravelPolar\Order;
use Danestves\LaravelPolar\Subscription;
use Illuminate\Support\Facades\Log;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob;

class ProcessWebhook extends ProcessWebhookJob
{
    public function handle(): void
    {
        $decoded = json_decode($this->webhookCall, true);
        $payload = $decoded['payload'];
        $type = $payload['type'];
        $data = $payload['data'];

        WebhookReceived::dispatch($payload);

        match ($type) {
            'order.created' => $this->handleOrderCreated($data),
            'order.updated' => $this->handleOrderUpdated($data),
            'subscription.created' => $this->handleSubscriptionCreated($data),
            'subscription.updated' => $this->handleSubscriptionUpdated($data),
            'subscription.active' => $this->handleSubscriptionActive($data),
            'subscription.canceled' => $this->handleSubscriptionCanceled($data),
            'subscription.revoked' => $this->handleSubscriptionRevoked($data),
            'benefit_grant.created' => $this->handleBenefitGrantCreated($data),
            'benefit_grant.updated' => $this->handleBenefitGrantUpdated($data),
            'benefit_grant.revoked' => $this->handleBenefitGrantRevoked($data),
            default => Log::info("Unknown event type: $type"),
        };

        WebhookHandled::dispatch($payload);

        // Acknowledge you received the response
        http_response_code(200);
    }

    /**
     * Handle the order created event.
     *
     * @param  array<string, mixed>  $payload
     */
    private function handleOrderCreated(array $payload): void
    {
        $billable = $this->resolveBillable($payload);

        $order = $billable->orders()->create([ // @phpstan-ignore-line class.notFound - the property is found in the billable model
            'polar_id' => $payload['id'],
            'status' => $payload['status'],
            'amount' => $payload['amount'],
            'tax_amount' => $payload['tax_amount'],
            'refunded_amount' => $payload['refunded_amount'],
            'refunded_tax_amount' => $payload['refunded_tax_amount'],
            'currency' => $payload['currency'],
            'billing_reason' => $payload['billing_reason'],
            'customer_id' => $payload['customer_id'],
            'product_id' => $payload['product_id'],
            'product_price_id' => $payload['product_price_id'],
            'ordered_at' => Carbon::make($payload['created_at']),
        ]);
       Log::info("created" .$order);
        OrderCreated::dispatch($billable, $order, $payload); // @phpstan-ignore-line argument.type - Billable is a instance of a model
    }

    /**
     * Handle the order updated event.
     *
     * @param  array<string, mixed>  $payload
     */
    private function handleOrderUpdated(array $payload): void
    {
        $billable = $this->resolveBillable($payload);

        if (!($order = $this->findOrder($payload['id'])) instanceof LaravelPolar::$orderModel) {
            return;
        }

        $status = $payload['status'];
        $isRefunded = $status === OrderStatus::Refunded->value || $status === OrderStatus::PartiallyRefunded->value;

        $order->sync([
            ...$payload,
            'status' => $status,
            'refunded_at' => $isRefunded ? Carbon::make($payload['refunded_at']) : null,
        ]);
         Log::info( "updated" . $order);
        OrderUpdated::dispatch($billable, $order, $payload, $isRefunded); // @phpstan-ignore-line argument.type - Billable is a instance of a model
    }

    /**
     * Handle the subscription created event.
     *
     * @param  array<string, mixed>  $payload
     */
    /**
 * Handle the subscription created event.
 *
 * @param  array<string, mixed>  $payload
 */
private function handleSubscriptionCreated(array $payload): void
{
    $customerMetadata = $payload['customer']['metadata'];
    $billable = $this->resolveBillable($payload);
     Log::warning($payload);
    $subscription = $billable->subscriptions()->create([
        'type' => $customerMetadata['subscription_type'] ?? "default",
        'polar_id' => $payload['id'],
        'status' => $payload['status'],
        'product_id' => $payload['product_id'],
        'price_id' => $payload['price_id'],
        'current_period_end' => $payload['current_period_end'] ? Carbon::make($payload['current_period_end']) : null,
        'ends_at' => $payload['ends_at'] ? Carbon::make($payload['ends_at']) : null,
    ]);
     Log::warning($subscription);

    if ($billable->customer->polar_id === null) {
        $billable->customer->update(['polar_id' => $payload['customer_id']]);
    }

    // تحديث email_verified_at للمستخدم بناءً على البريد الإلكتروني
    $email = $payload['customer']['email'];
    $user = \App\Models\User::where('email', $email)->first();
    if ($user) {
        $user->update(['email_verified_at' => Carbon::now()]);
        $user->save();
    } else {
        Log::warning("User with email {$email} not found for subscription created event.");
    }

    Log::info("created " . $user ." email_verified_at ".Carbon::now() );
    SubscriptionCreated::dispatch($billable, $subscription, $payload);
}

    /**
     * Handle the subscription updated event.
     *
     * @param  array<string, mixed>  $payload
     */
    /**
 * Handle the subscription updated event.
 *
 * @param  array<string, mixed>  $payload
 */
private function handleSubscriptionUpdated(array $payload): void
{
    if (!($subscription = $this->findSubscription($payload['id'])) instanceof LaravelPolar::$subscriptionModel) {
        return;
    }

    $subscription->sync($payload);

    // تحديث email_verified_at للمستخدم بناءً على البريد الإلكتروني
    $email = $payload['customer']['email'];
    $user = \App\Models\User::where('email', $email)->first();
    if ($user) {
        $user->update(['email_verified_at' => Carbon::now()]);
        $user->save();
    } else {
        Log::warning("User with email {$email} not found for subscription updated event.");
    }

    Log::info("updated" . $subscription);
    SubscriptionUpdated::dispatch($subscription->billable, $subscription, $payload);
}

    /**
     * Handle the subscription active event.
     *
     * @param  array<string, mixed>  $payload
     */
    private function handleSubscriptionActive(array $payload): void
    {
        if (!($subscription = $this->findSubscription($payload['id'])) instanceof LaravelPolar::$subscriptionModel) {
            return;
        }

        $subscription->sync($payload);

        SubscriptionActive::dispatch($subscription->billable, $subscription, $payload); // @phpstan-ignore-line argument.type - Billable is a instance of a model
    }

    /**
     * Handle the subscription canceled event.
     *
     * @param  array<string, mixed>  $payload
     */
    private function handleSubscriptionCanceled(array $payload): void
    {
        if (!($subscription = $this->findSubscription($payload['id'])) instanceof LaravelPolar::$subscriptionModel) {
            return;
        }

        $subscription->sync($payload);
        Log::info( "canceled" . $subscription);
        SubscriptionCanceled::dispatch($subscription->billable, $subscription, $payload); // @phpstan-ignore-line argument.type - Billable is a instance of a model
    }

    /**
     * Handle the subscription revoked event.
     *
     * @param  array<string, mixed>  $payload
     */
    private function handleSubscriptionRevoked(array $payload): void
    {
        if (!($subscription = $this->findSubscription($payload['id'])) instanceof LaravelPolar::$subscriptionModel) {
            return;
        }

        $subscription->sync($payload);

        SubscriptionRevoked::dispatch($subscription->billable, $subscription, $payload); // @phpstan-ignore-line argument.type - Billable is a instance of a model
    }

    /**
     * Handle the benefit grant created event.
     *
     * @param  array<string, mixed>  $payload
     */
    private function handleBenefitGrantCreated(array $payload): void
    {
        $billable = $this->resolveBillable($payload);

        BenefitGrantCreated::dispatch($billable, $payload); // @phpstan-ignore-line argument.type - Billable is a instance of a model
    }

    /**
     * Handle the benefit grant updated event.
     *
     * @param  array<string, mixed>  $payload
     */
    private function handleBenefitGrantUpdated(array $payload): void
    {
        $billable = $this->resolveBillable($payload);

        BenefitGrantUpdated::dispatch($billable, $payload); // @phpstan-ignore-line argument.type - Billable is a instance of a model
    }

    /**
     * Handle the benefit grant revoked event.
     *
     * @param  array<string, mixed>  $payload
     */
    private function handleBenefitGrantRevoked(array $payload): void
    {
        $billable = $this->resolveBillable($payload);

        BenefitGrantRevoked::dispatch($billable, $payload); // @phpstan-ignore-line argument.type - Billable is a instance of a model
    }

    /**
     * Resolve the billable from the payload.
     *
     * @param  array<string, mixed>  $payload
     * @return \Danestves\LaravelPolar\Billable
     *
     * @throws InvalidMetadataPayload
     */
    private function resolveBillable(array $payload) // @phpstan-ignore-line return.trait - Billable is used in the user final code
    {
        $customerMetadata = $payload['customer']['metadata'] ?? null;
        $productMetadata = $payload['product']['metadata'] ?? null;
        $useremail = $payload['user']['email'] ?? null;
        if ((!isset($customerMetadata) || !is_array($customerMetadata) || !isset($customerMetadata['billable_id'], $customerMetadata['billable_type'])) && !isset($productMetadata["price_status"])) {
             Log::warning($productMetadata);
            throw new InvalidMetadataPayload();
        }
        
        
        if(isset($productMetadata["price_status"])){
            $email = $payload['customer']['email'];
            $user = \App\Models\User::where('email', $email)->first();
            if ($user) {
                $billableId = $user->id;
                $billableType = "App\Models\User";
                $customerId = (string) $payload['customer_id'];
            } else {
                Log::warning("User with email {$email} not found for subscription updated event.");
            }
        }
        else{
            $billableId = $customerMetadata['billable_id'] ?? null;
        $billableType= (string) $customerMetadata['billable_type'] ?? null;
        $customerId = (string) $payload['customer_id'] ?? null;
        }
        

        return $this->findOrCreateCustomer(
            $billableId,
            $billableType,
            $customerId,
        );
    }

    /**
     * Find or create a customer.
     *
     * @return \Danestves\LaravelPolar\Billable
     */
    private function findOrCreateCustomer(int|string $billableId, string $billableType, string $customerId) // @phpstan-ignore-line return.trait - Billable is used in the user final code
    {
        return LaravelPolar::$customerModel::firstOrCreate([
            'billable_id' => $billableId,
            'billable_type' => $billableType,
        ], [
            'polar_id' => $customerId,
        ])->billable;
    }

    private function findSubscription(string $subscriptionId): ?Subscription
    {
        return LaravelPolar::$subscriptionModel::firstWhere('polar_id', $subscriptionId);
    }

    private function findOrder(string $orderId): ?Order
    {
        return LaravelPolar::$orderModel::firstWhere('polar_id', $orderId);
    }
}
