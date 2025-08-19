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
        
        // استخراج البيانات باستخدام extractMetadata
        $metadata = $this->extractMetadata($payload);
        $type = $metadata['type'] ?? 'unknown';

        WebhookReceived::dispatch($decoded['payload'] ?? $decoded);

        match ($type) {
            'order.created' => $this->handleOrderCreated($metadata),
            'order.updated' => $this->handleOrderUpdated($metadata),
            'subscription.created' => $this->handleSubscriptionCreated($metadata),
            'subscription.updated' => $this->handleSubscriptionUpdated($metadata),
            'subscription.active' => $this->handleSubscriptionActive($metadata),
            'subscription.canceled' => $this->handleSubscriptionCanceled($metadata),
            'subscription.revoked' => $this->handleSubscriptionRevoked($metadata),
            'benefit_grant.created' => $this->handleBenefitGrantCreated($metadata),
            'benefit_grant.updated' => $this->handleBenefitGrantUpdated($metadata),
            'benefit_grant.revoked' => $this->handleBenefitGrantRevoked($metadata),
            default => Log::info("Unknown event type: $type"),
        };

        WebhookHandled::dispatch($decoded['payload'] ?? $decoded);

        // Acknowledge you received the response
        http_response_code(200);
    }

    /**
     * Handle the order created event.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function handleOrderCreated(array $metadata): void
    {
        $billable = $this->resolveBillable($metadata);

        $order = $billable->orders()->create([
            'billable_type' => $metadata['billable_type'],
            'billable_id' => $metadata['billable_id'],
            'polar_id' => $metadata['order_id'] ?? $metadata['subscription_id'],
            'status' => $metadata['status'] ?? 'unknown',
            'amount' => $metadata['amount'] ?? 0,
            'tax_amount' => $metadata['tax_amount'] ?? 0,
            'refunded_amount' => $metadata['refunded_amount'] ?? 0,
            'refunded_tax_amount' => $metadata['refunded_tax_amount'] ?? 0,
            'currency' => $metadata['currency'] ?? 'usd',
            'billing_reason' => $metadata['billing_reason'] ?? null,
            'customer_id' => $metadata['customer_id'],
            'product_id' => $metadata['product_id'],
            'product_price_id' => $metadata['product_price_id'],
            'refunded_at' => null,
            'ordered_at' => Carbon::make($metadata['started_at'] ?? $metadata['created_at'] ?? now()),
        ]);

        OrderCreated::dispatch($billable, $order, $metadata);
    }

    /**
     * Handle the order updated event.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function handleOrderUpdated(array $metadata): void
    {
        $billable = $this->resolveBillable($metadata);

        if (!($order = $this->findOrder($metadata['order_id'] ?? $metadata['subscription_id'] ?? '')) instanceof LaravelPolar::$orderModel) {
            return;
        }

        $status = $metadata['status'] ?? 'unknown';
        $isRefunded = $status === OrderStatus::Refunded->value || $status === OrderStatus::PartiallyRefunded->value;

        $order->update([
            'billable_type' => $metadata['billable_type'],
            'billable_id' => $metadata['billable_id'],
            'status' => $status,
            'amount' => $metadata['amount'] ?? $order->amount,
            'tax_amount' => $metadata['tax_amount'] ?? $order->tax_amount,
            'refunded_amount' => $metadata['refunded_amount'] ?? $order->refunded_amount,
            'refunded_tax_amount' => $metadata['refunded_tax_amount'] ?? $order->refunded_tax_amount,
            'currency' => $metadata['currency'] ?? $order->currency,
            'billing_reason' => $metadata['billing_reason'] ?? $order->billing_reason,
            'customer_id' => $metadata['customer_id'] ?? $order->customer_id,
            'product_id' => $metadata['product_id'] ?? $order->product_id,
            'product_price_id' => $metadata['product_price_id'] ?? $order->product_price_id,
            'refunded_at' => $isRefunded ? Carbon::make($metadata['canceled_at'] ?? $metadata['refunded_at'] ?? now()) : $order->refunded_at,
        ]);

        OrderUpdated::dispatch($billable, $order, $metadata, $isRefunded);
    }

    /**
     * Handle the subscription created event.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function handleSubscriptionCreated(array $metadata): void
    {
        $billable = $this->resolveBillable($metadata);
        
        $subscription = $billable->subscriptions()->create([
            'billable_type' => $metadata['billable_type'],
            'billable_id' => $metadata['billable_id'],
            'type' => $metadata['subscription_type'] ?? 'default',
            'polar_id' => $metadata['subscription_id'],
            'status' => $metadata['status'],
            'product_id' => $metadata['product_id'],
            'price_id' => $metadata['price_id'],
            'current_period_end' => $metadata['current_period_end'] ? Carbon::make($metadata['current_period_end']) : null,
            'trial_ends_at' => $metadata['trial_ends_at'] ? Carbon::make($metadata['trial_ends_at']) : null,
            'ends_at' => $metadata['ends_at'] ? Carbon::make($metadata['ends_at']) : null,
        ]);

        // تحديث polar_id للعميل إذا لم يكن موجوداً
        if ($billable->customer && $billable->customer->polar_id === null) {
            $billable->customer->update(['polar_id' => $metadata['customer_id']]);
        }

        SubscriptionCreated::dispatch($billable, $subscription, $metadata);
    }

    /**
     * Handle the subscription updated event.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function handleSubscriptionUpdated(array $metadata): void
    {
        if (!($subscription = $this->findSubscription($metadata['subscription_id'] ?? '')) instanceof LaravelPolar::$subscriptionModel) {
            return;
        }

        $billable = $this->resolveBillable($metadata);

        $subscription->update([
            'billable_type' => $metadata['billable_type'],
            'billable_id' => $metadata['billable_id'],
            'status' => $metadata['status'],
            'product_id' => $metadata['product_id'],
            'price_id' => $metadata['price_id'],
            'current_period_end' => $metadata['current_period_end'] ? Carbon::make($metadata['current_period_end']) : null,
            'trial_ends_at' => $metadata['trial_ends_at'] ? Carbon::make($metadata['trial_ends_at']) : null,
            'ends_at' => $metadata['ends_at'] ? Carbon::make($metadata['ends_at']) : null,
        ]);

        SubscriptionUpdated::dispatch($subscription->billable, $subscription, $metadata);
    }

    /**
     * Handle the subscription active event.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function handleSubscriptionActive(array $metadata): void
    {
        if (!($subscription = $this->findSubscription($metadata['subscription_id'] ?? '')) instanceof LaravelPolar::$subscriptionModel) {
            return;
        }

        $billable = $this->resolveBillable($metadata);

        $subscription->update([
            'billable_type' => $metadata['billable_type'],
            'billable_id' => $metadata['billable_id'],
            'status' => $metadata['status'],
            'current_period_end' => $metadata['current_period_end'] ? Carbon::make($metadata['current_period_end']) : null,
            'trial_ends_at' => $metadata['trial_ends_at'] ? Carbon::make($metadata['trial_ends_at']) : null,
            'ends_at' => $metadata['ends_at'] ? Carbon::make($metadata['ends_at']) : null,
        ]);

        SubscriptionActive::dispatch($subscription->billable, $subscription, $metadata);
    }

    /**
     * Handle the subscription canceled event.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function handleSubscriptionCanceled(array $metadata): void
    {
        if (!($subscription = $this->findSubscription($metadata['subscription_id'] ?? '')) instanceof LaravelPolar::$subscriptionModel) {
            return;
        }

        $billable = $this->resolveBillable($metadata);

        $subscription->update([
            'billable_type' => $metadata['billable_type'],
            'billable_id' => $metadata['billable_id'],
            'status' => $metadata['status'],
            'ends_at' => $metadata['canceled_at'] ? Carbon::make($metadata['canceled_at']) : null,
            'current_period_end' => $metadata['current_period_end'] ? Carbon::make($metadata['current_period_end']) : null,
        ]);

        SubscriptionCanceled::dispatch($subscription->billable, $subscription, $metadata);
    }

    /**
     * Handle the subscription revoked event.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function handleSubscriptionRevoked(array $metadata): void
    {
        if (!($subscription = $this->findSubscription($metadata['subscription_id'] ?? '')) instanceof LaravelPolar::$subscriptionModel) {
            return;
        }

        $billable = $this->resolveBillable($metadata);

        $subscription->update([
            'billable_type' => $metadata['billable_type'],
            'billable_id' => $metadata['billable_id'],
            'status' => 'revoked',
            'ends_at' => now(),
            'current_period_end' => $metadata['current_period_end'] ? Carbon::make($metadata['current_period_end']) : null,
        ]);

        SubscriptionRevoked::dispatch($subscription->billable, $subscription, $metadata);
    }

    /**
     * Handle the benefit grant created event.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function handleBenefitGrantCreated(array $metadata): void
    {
        $billable = $this->resolveBillable($metadata);
        BenefitGrantCreated::dispatch($billable, $metadata);
    }

    /**
     * Handle the benefit grant updated event.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function handleBenefitGrantUpdated(array $metadata): void
    {
        $billable = $this->resolveBillable($metadata);
        BenefitGrantUpdated::dispatch($billable, $metadata);
    }

    /**
     * Handle the benefit grant revoked event.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function handleBenefitGrantRevoked(array $metadata): void
    {
        $billable = $this->resolveBillable($metadata);
        BenefitGrantRevoked::dispatch($billable, $metadata);
    }

    /**
     * استخراج البيانات المهمة من الـ payload بطريقة دقيقة
     * 
     * @param array $payload
     * @return array
     */
    private function extractMetadata(array $payload): array
{
    // البيانات الأساسية من المستوى الأعلى
    $data = $payload['data'] ?? $payload['payload'] ?? $payload;
    
    // استخراج نوع الحدث
    $type = $this->extractValue($payload, 'type') ?? 'unknown';
    
    // استخراج معرف العميل
    $customerId = $this->extractValue($data, 'customer_id') ?? 
                  $this->extractValue($data, 'customer.id') ?? 
                  $this->extractValue($data, 'user_id') ?? 
                  $this->extractValue($data, 'user.id') ?? null;
    
    // استخراج البريد الإلكتروني
    $email = $this->extractValue($data, 'customer.email') ?? 
             $this->extractValue($data, 'user.email') ?? null;
    Log::info($email);
    // استخراج معلومات المنتج
    $productId = $this->extractValue($data, 'product_id') ?? 
                 $this->extractValue($data, 'product.id') ?? null;
    
    $productName = $this->extractValue($data, 'product.name') ?? 'unknown';
    
    // استخراج معلومات السعر
    $priceId = $this->extractValue($data, 'price_id') ?? 
               $this->extractValue($data, 'price.id') ?? null;
    
    $amount = $this->extractValue($data, 'amount') ?? 0;
    
    $currency = $this->extractValue($data, 'currency') ?? 'usd';
    
    // استخراج metadata من مصادر مختلفة
    $subscriptionMetadata = $this->extractValue($data, 'metadata') ?? [];
    $customerMetadata = $this->extractValue($data, 'customer.metadata') ?? [];
    $productMetadata = $this->extractValue($data, 'product.metadata') ?? [];
    
    // دمج جميع metadata
    $allMetadata = array_merge($subscriptionMetadata, $customerMetadata, $productMetadata);
    
    // استخراج معلومات الاشتراك
    $subscriptionId = $this->extractValue($data, 'id') ?? null;
    $status = $this->extractValue($data, 'status') ?? 'unknown';
    $recurringInterval = $this->extractValue($data, 'recurring_interval') ?? null;
    
    // استخراج التواريخ المهمة
    $createdAt = $this->extractValue($data, 'created_at') ?? null;
    $modifiedAt = $this->extractValue($data, 'modified_at') ?? null;
    $currentPeriodStart = $this->extractValue($data, 'current_period_start') ?? null;
    $currentPeriodEnd = $this->extractValue($data, 'current_period_end') ?? null;
    $startedAt = $this->extractValue($data, 'started_at') ?? null;
    $endsAt = $this->extractValue($data, 'ends_at') ?? null;
    $endedAt = $this->extractValue($data, 'ended_at') ?? null;
    $canceledAt = $this->extractValue($data, 'canceled_at') ?? null;
    
    // معلومات إضافية للاشتراك
    $checkoutId = $this->extractValue($data, 'checkout_id') ?? null;
    $cancelAtPeriodEnd = $this->extractValue($data, 'cancel_at_period_end') ?? false;
    $customerCancellationReason = $this->extractValue($data, 'customer_cancellation_reason') ?? null;
    $customerCancellationComment = $this->extractValue($data, 'customer_cancellation_comment') ?? null;
    
    // معلومات المنظمة
    $organizationId = $this->extractValue($data, 'customer.organization_id') ?? 
                     $this->extractValue($data, 'product.organization_id') ?? null;
    
    // معلومات العنوان
    $billingAddress = $this->extractValue($data, 'customer.billing_address') ?? [];
    $country = $this->extractValue($billingAddress, 'country') ?? null;
    
    // تحديد نوع السعر
    $priceType = $this->extractValue($data, 'price.type') ?? 
                 $this->extractValue($data, 'price.amount_type') ?? 'unknown';
    
    // تحديد حالة السعر من metadata
    $priceStatus = $this->extractValue($allMetadata, 'price_status') ?? 
                   ($amount == 0 ? 'free' : 'paid');
    
    // استخراج معلومات العميل الإضافية
    $customerCreatedAt = $this->extractValue($data, 'customer.created_at') ?? null;
    $customerModifiedAt = $this->extractValue($data, 'customer.modified_at') ?? null;
    $customerDeletedAt = $this->extractValue($data, 'customer.deleted_at') ?? null;
    
    // استخراج معلومات المنتج الإضافية
    $productDescription = $this->extractValue($data, 'product.description') ?? null;
    $productIsRecurring = $this->extractValue($data, 'product.is_recurring') ?? null;
    $productIsArchived = $this->extractValue($data, 'product.is_archived') ?? null;
    $productCreatedAt = $this->extractValue($data, 'product.created_at') ?? null;
    $productModifiedAt = $this->extractValue($data, 'product.modified_at') ?? null;
    $product_price_id =  $this->extractValue($data, 'product_price.id') ?? null;
    // استخراج معلومات السعر الإضافية
    $priceIsArchived = $this->extractValue($data, 'price.is_archived') ?? null;
    $priceCreatedAt = $this->extractValue($data, 'price.created_at') ?? null;
    $priceModifiedAt = $this->extractValue($data, 'price.modified_at') ?? null;
    $priceRecurringInterval = $this->extractValue($data, 'price.recurring_interval') ?? null;
    
    // استخراج معلومات العنوان التفصيلية
    $billingAddressLine1 = $this->extractValue($billingAddress, 'line1') ?? null;
    $billingAddressLine2 = $this->extractValue($billingAddress, 'line2') ?? null;
    $billingAddressPostalCode = $this->extractValue($billingAddress, 'postal_code') ?? null;
    $billingAddressCity = $this->extractValue($billingAddress, 'city') ?? null;
    $billing_reason = $this->extractValue($data, 'billing_reason') ?? null;
    $billingAddressState = $this->extractValue($billingAddress, 'state') ?? null;
     $user = \App\Models\User::where('email', $email)->first();
    $billable_id = $user->id;
    $trial_ends_at =   null;
           
         
    $extractedMetadata = [
        // نوع الحدث
        'type' => $type,
        
        // معرفات العميل
        'customer_id' => $customerId,
        'user_id' => $this->extractValue($data, 'user_id') ?? null,
        'email' => $email,
        
        // معرفات الاشتراك والمنتج
        'subscription_id' => $subscriptionId,
        'product_id' => $productId,
        'product_name' => $productName,
        'price_id' => $priceId,
        
        // معلومات السعر والفوترة
        'amount' => $amount,
        'currency' => $currency,
        'price_type' => $priceType,
        'price_status' => $priceStatus,
        'recurring_interval' => $recurringInterval,
        
        // حالة الاشتراك
        'status' => $status,
        'is_active' => $status === 'active',
        'is_free' => $amount == 0 || $priceStatus === 'free',
        'cancel_at_period_end' => $cancelAtPeriodEnd,
        'is_canceled' => !empty($canceledAt),
        
        // التواريخ الرئيسية
        'created_at' => $createdAt,
        'modified_at' => $modifiedAt,
        'current_period_start' => $currentPeriodStart,
        'current_period_end' => $currentPeriodEnd,
        'started_at' => $startedAt,
        'ends_at' => $endsAt,
        'ended_at' => $endedAt,
        'canceled_at' => $canceledAt,
        "trial_ends_at"=> $trial_ends_at,
        
        // معلومات الإلغاء
        'customer_cancellation_reason' => $customerCancellationReason,
        'customer_cancellation_comment' => $customerCancellationComment,
        
        // معرفات إضافية
        'checkout_id' => $checkoutId,
        'organization_id' => $organizationId,
        
        // معلومات جغرافية وعنوان
        'country' => $country,
        'billing_address' => $billingAddress,
        'billing_address_line1' => $billingAddressLine1,
        'billing_address_line2' => $billingAddressLine2,
        'billing_address_postal_code' => $billingAddressPostalCode,
        'billing_address_city' => $billingAddressCity,
        'billing_address_state' => $billingAddressState,
        'billing_reason' =>$billing_reason,
        
        // metadata مدمجة
        'metadata' => $allMetadata,
        'subscription_metadata' => $subscriptionMetadata,
        'customer_metadata' => $customerMetadata,
        'product_metadata' => $productMetadata,
        
        // معلومات الخصم
        'has_discount' => !empty($this->extractValue($data, 'discount_id')),
        'discount_id' => $this->extractValue($data, 'discount_id') ?? null,
        'discount' => $this->extractValue($data, 'discount') ?? null,
        
        // الحقول المخصصة
        'custom_fields' => $this->extractValue($data, 'custom_field_data') ?? [],
        
        // معلومات المستخدم التفصيلية
        'user_public_name' => $this->extractValue($data, 'user.public_name') ?? null,
        'user_avatar_url' => $this->extractValue($data, 'user.avatar_url') ?? null,
        'user_github_username' => $this->extractValue($data, 'user.github_username') ?? null,
        
        // معلومات العميل التفصيلية
        'customer_name' => $this->extractValue($data, 'customer.name') ?? null,
        'customer_avatar_url' => $this->extractValue($data, 'customer.avatar_url') ?? null,
        'customer_email_verified' => $this->extractValue($data, 'customer.email_verified') ?? false,
        'customer_external_id' => $this->extractValue($data, 'customer.external_id') ?? null,
        'customer_tax_id' => $this->extractValue($data, 'customer.tax_id') ?? null,
        'customer_created_at' => $customerCreatedAt,
        'customer_modified_at' => $customerModifiedAt,
        'customer_deleted_at' => $customerDeletedAt,
        
        // معلومات المنتج التفصيلية
        'product_description' => $productDescription,
        'product_is_recurring' => $productIsRecurring,
        'product_is_archived' => $productIsArchived,
        'product_recurring_interval' => $this->extractValue($data, 'product.recurring_interval') ?? null,
        'product_created_at' => $productCreatedAt,
        'product_modified_at' => $productModifiedAt,
        
        // معلومات السعر التفصيلية
        'price_is_archived' => $priceIsArchived,
        'price_created_at' => $priceCreatedAt,
        'price_modified_at' => $priceModifiedAt,
        'price_recurring_interval' => $priceRecurringInterval,
        'price_product_id' => $this->extractValue($data, 'price.product_id') ?? null,
        
        // مصفوفات إضافية
        'product_prices' => $this->extractValue($data, 'product.prices') ?? [],
        'product_price_id'=>$product_price_id,
        'product_benefits' => $this->extractValue($data, 'product.benefits') ?? [],
        'product_medias' => $this->extractValue($data, 'product.medias') ?? [],
        'product_attached_custom_fields' => $this->extractValue($data, 'product.attached_custom_fields') ?? [],
        'prices' => $this->extractValue($data, 'prices') ?? [],
        'meters' => $this->extractValue($data, 'meters') ?? [],
        
        // للاستخدام في الـ billable resolution
        'billable_type' => 'App\Models\User',
         "billable_id" =>$billable_id,
        
        
        // معلومات إضافية للتحليل
        'has_custom_fields' => !empty($this->extractValue($data, 'custom_field_data')),
        'has_benefits' => !empty($this->extractValue($data, 'product.benefits')),
        'has_medias' => !empty($this->extractValue($data, 'product.medias')),
        'is_product_archived' => $productIsArchived === true,
        'is_price_archived' => $priceIsArchived === true,
        'customer_has_tax_id' => !empty($this->extractValue($data, 'customer.tax_id')),
        'customer_has_external_id' => !empty($this->extractValue($data, 'customer.external_id')),
        'user_has_github' => !empty($this->extractValue($data, 'user.github_username')),
    ];
    
    // تسجيل المعلومات المستخرجة للمراجعة
    \Log::info('Extracted metadata:', [
        'type' => $type,
        'customer_id' => $customerId,
        'email' => $email,
        'subscription_id' => $subscriptionId,
        'product_name' => $productName,
        'amount' => $amount,
        'price_status' => $priceStatus,
        'status' => $status,
        'country' => $country,
        'organization_id' => $organizationId,
        'is_free' => $extractedMetadata['is_free'],
        'cancel_at_period_end' => $cancelAtPeriodEnd,
        'metadata_keys' => array_keys($allMetadata),
        'total_extracted_fields' => count($extractedMetadata)
    ]);
    
    return $extractedMetadata;
}

    /**
     * Resolve the billable from the payload.
     *
     * @param  array<string, mixed>  $metadata
     * @return \Danestves\LaravelPolar\Billable
     *
     * @throws InvalidMetadataPayload
     */
    private function resolveBillable(array $metadata)
    {
        $customerId = $metadata['customer_id'];
        $email = $metadata['email'];
        $customerMetadata = $metadata['customer_metadata'];
        
        Log::info('Resolving billable:', [
            'customer_id' => $customerId,
            'email' => $email,
            'has_metadata' => !empty($customerMetadata),
        ]);
        
        // البحث بالبريد الإلكتروني أولاً
        if ($email) {
            $user = \App\Models\User::where('email', $email)->first();
            
            if ($user) {
                Log::info('Found user by email:', [
                    'email' => $email,
                    'user_id' => $user->id,
                ]);
                
                return $this->findOrCreateCustomer(
                    $user->id,
                    'App\Models\User',
                    $customerId,
                );
            }
        }
        
        // الرجوع إلى metadata القديمة
        if (!empty($customerMetadata['billable_id']) && !empty($customerMetadata['billable_type'])) {
            return $this->findOrCreateCustomer(
                $customerMetadata['billable_id'],
                (string) $customerMetadata['billable_type'],
                (string) $customerId,
            );
        }
        
        throw new InvalidMetadataPayload(
            'Unable to resolve billable: missing email or legacy metadata. ' .
            'Available data: ' . json_encode([
                'has_customer_id' => !empty($customerId),
                'has_email' => !empty($email),
                'customer_id' => $customerId,
                'email' => $email
            ])
        );
    }

  /**
     * استخراج قيمة من المصفوفة باستخدام مسار نقطي
     */
    private function extractValue(array $data, string $path)
    {
        $keys = explode('.', $path);
        $current = $data;
        
        foreach ($keys as $key) {
            if (!is_array($current) || !isset($current[$key])) {
                return null;
            }
            $current = $current[$key];
        }
        
        return $current;
    }


    /**
     * Find or create a customer.
     *
     * @return \Danestves\LaravelPolar\Billable
     */
    private function findOrCreateCustomer(int|string $billableId, string $billableType, string $customerId)
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
?>
