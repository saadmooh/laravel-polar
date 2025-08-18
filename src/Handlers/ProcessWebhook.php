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
        // استخراج البيانات باستخدام extractMetadata بدلاً من $payload و $data
        $metadata = $this->extractMetadata($decoded);
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

        $order = $billable->orders()->create([ // @phpstan-ignore-line class.notFound - the property is found in the billable model
            'polar_id' => $metadata['subscription_id'] ?? null,
            'status' => $metadata['status'] ?? 'unknown',
            'amount' => $metadata['amount'] ?? 0,
            'tax_amount' => $metadata['tax_amount'] ?? 0, // غير موجود في الـ JSON، قيمة افتراضية
            'refunded_amount' => $metadata['refunded_amount'] ?? 0, // غير موجود في الـ JSON، قيمة افتراضية
            'refunded_tax_amount' => $metadata['refunded_tax_amount'] ?? 0, // غير موجود في الـ JSON، قيمة افتراضية
            'currency' => $metadata['currency'] ?? 'usd',
            'billing_reason' => $metadata['billing_reason'] ?? null, // غير موجود في الـ JSON، قيمة افتراضية
            'customer_id' => $metadata['customer_id'] ?? null,
            'product_id' => $metadata['product_id'] ?? null,
            'ordered_at' => Carbon::make($metadata['started_at'] ?? null),
        ]);

        OrderCreated::dispatch($billable, $order, $metadata); // @phpstan-ignore-line argument.type - Billable is a instance of a model
    }

    /**
     * Handle the order updated event.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function handleOrderUpdated(array $metadata): void
    {
        $billable = $this->resolveBillable($metadata);

        if (!($order = $this->findOrder($metadata['subscription_id'] ?? '')) instanceof LaravelPolar::$orderModel) {
            return;
        }

        $status = $metadata['status'] ?? 'unknown';
        $isRefunded = $status === OrderStatus::Refunded->value || $status === OrderStatus::PartiallyRefunded->value;

        $order->sync([
            ...$metadata,
            'status' => $status,
            'refunded_at' => $isRefunded ? Carbon::make($metadata['canceled_at'] ?? null) : null,
        ]);

        OrderUpdated::dispatch($billable, $order, $metadata, $isRefunded); // @phpstan-ignore-line argument.type - Billable is a instance of a model
    }

    /**
     * Handle the subscription created event.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function handleSubscriptionCreated(array $metadata): void
    {
        // الحصول على billable باستخدام resolveBillable
        $billable = $this->resolveBillable($metadata);
        
        // إنشاء الاشتراك
        $subscription = $billable->subscriptions()->create([ // @phpstan-ignore-line class.notFound - the property is found in the billable model
            'type' => $metadata['subscription_metadata']['subscription_type'] ?? 'default',
            'polar_id' => $metadata['subscription_id'],
            'status' => $metadata['status'],
            'product_id' => $metadata['product_id'],
            'current_period_end' => $metadata['current_period_end'] ? Carbon::make($metadata['current_period_end']) : null,
            'ends_at' => $metadata['ends_at'] ? Carbon::make($metadata['ends_at']) : null,
        ]);

        if ($billable->customer->polar_id === null) { // @phpstan-ignore-line property.notFound - the property is found in the billable model
            $billable->customer->update(['polar_id' => $metadata['customer_id']]); // @phpstan-ignore-line property.notFound - the property is found in the billable model
        }

        SubscriptionCreated::dispatch($billable, $subscription, $metadata); // @phpstan-ignore-line argument.type - Billable is a instance of a model
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

        $subscription->sync($metadata);

        SubscriptionUpdated::dispatch($subscription->billable, $subscription, $metadata); // @phpstan-ignore-line argument.type - Billable is a instance of a model
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

        $subscription->sync($metadata);

        SubscriptionActive::dispatch($subscription->billable, $subscription, $metadata); // @phpstan-ignore-line argument.type - Billable is a instance of a model
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

        $subscription->sync($metadata);

        SubscriptionCanceled::dispatch($subscription->billable, $subscription, $metadata); // @phpstan-ignore-line argument.type - Billable is a instance of a model
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

        $subscription->sync($metadata);

        SubscriptionRevoked::dispatch($subscription->billable, $subscription, $metadata); // @phpstan-ignore-line argument.type - Billable is a instance of a model
    }

    /**
     * Handle the benefit grant created event.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function handleBenefitGrantCreated(array $metadata): void
    {
        $billable = $this->resolveBillable($metadata);

        BenefitGrantCreated::dispatch($billable, $metadata); // @phpstan-ignore-line argument.type - Billable is a instance of a model
    }

    /**
     * Handle the benefit grant updated event.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function handleBenefitGrantUpdated(array $metadata): void
    {
        $billable = $this->resolveBillable($metadata);

        BenefitGrantUpdated::dispatch($billable, $metadata); // @phpstan-ignore-line argument.type - Billable is a instance of a model
    }

    /**
     * Handle the benefit grant revoked event.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function handleBenefitGrantRevoked(array $metadata): void
    {
        $billable = $this->resolveBillable($metadata);

        BenefitGrantRevoked::dispatch($billable, $metadata); // @phpstan-ignore-line argument.type - Billable is a instance of a model
    }

    /**
     * استخراج البيانات المهمة من الـ payload بطريقة دقيقة بناءً على الـ JSON المقدم
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
        $currentPeriodStart = $this->extractValue($data, 'current_period_start') ?? null;
        $currentPeriodEnd = $this->extractValue($data, 'current_period_end') ?? null;
        $startedAt = $this->extractValue($data, 'started_at') ?? null;
        $endsAt = $this->extractValue($data, 'ends_at') ?? null;
        $canceledAt = $this->extractValue($data, 'canceled_at') ?? null;
        
        // معلومات إضافية
        $checkoutId = $this->extractValue($data, 'checkout_id') ?? null;
        $organizationId = $this->extractValue($data, 'customer.organization_id') ?? 
                         $this->extractValue($data, 'product.organization_id') ?? null;
        
        // معلومات العنوان
        $billingAddress = $this->extractValue($data, 'customer.billing_address') ?? [];
        $country = $this->extractValue($billingAddress, 'country') ?? null;
        
        // تحديد نوع السعر
        $priceType = $this->extractValue($data, 'price.amount_type') ?? 
                     $this->extractValue($data, 'price.type') ?? 'unknown';
        
        // تحديد حالة السعر من metadata
        $priceStatus = $this->extractValue($allMetadata, 'price_status') ?? 
                       ($amount == 0 ? 'free' : 'paid');
        
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
            
            // التواريخ
            'current_period_start' => $currentPeriodStart,
            'current_period_end' => $currentPeriodEnd,
            'started_at' => $startedAt,
            'ends_at' => $endsAt,
            'canceled_at' => $canceledAt,
            'is_canceled' => !empty($canceledAt),
            
            // معرفات إضافية
            'checkout_id' => $checkoutId,
            'organization_id' => $organizationId,
            
            // معلومات جغرافية
            'country' => $country,
            'billing_address' => $billingAddress,
            
            // metadata مدمجة
            'metadata' => $allMetadata,
            'subscription_metadata' => $subscriptionMetadata,
            'customer_metadata' => $customerMetadata,
            'product_metadata' => $productMetadata,
            
            // معلومات إضافية
            'has_discount' => !empty($this->extractValue($data, 'discount_id')),
            'discount_id' => $this->extractValue($data, 'discount_id') ?? null,
            'custom_fields' => $this->extractValue($data, 'custom_field_data') ?? [],
            
            // معلومات المستخدم
            'user_public_name' => $this->extractValue($data, 'user.public_name') ?? null,
            'user_avatar_url' => $this->extractValue($data, 'user.avatar_url') ?? null,
            'user_github_username' => $this->extractValue($data, 'user.github_username') ?? null,
            
            // معلومات العميل
            'customer_name' => $this->extractValue($data, 'customer.name') ?? null,
            'customer_avatar_url' => $this->extractValue($data, 'customer.avatar_url') ?? null,
            'customer_email_verified' => $this->extractValue($data, 'customer.email_verified') ?? false,
            'customer_external_id' => $this->extractValue($data, 'customer.external_id') ?? null,
            'customer_tax_id' => $this->extractValue($data, 'customer.tax_id') ?? null,
            
            // للاستخدام في الـ billable resolution
            'billable_type' => 'App\Models\User',
            'billable_id' => null,
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
            'metadata_keys' => array_keys($allMetadata)
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
    private function resolveBillable(array $metadata) // @phpstan-ignore-line return.trait - Billable is used in the user final code
    {
        $customerId = $metadata['customer_id'];
        $email = $metadata['email'];
        $customerMetadata = $metadata['customer_metadata'];
        
        // تسجيل المعلومات المستخرجة
        \Log::info('Resolved billable data:', [
            'customer_id' => $customerId,
            'email' => $email,
            'has_metadata' => !empty($customerMetadata),
            'metadata_keys' => array_keys($customerMetadata)
        ]);
        
        // التحقق من وجود البيانات المطلوبة
        if ($customerId && $email) {
            // البحث عن المستخدم باستخدام البريد الإلكتروني أو customer_id
            $user = \App\Models\User::where('email', $email)->first();
            
            if ($user) {
                $billableId = $user->id;
                $billableType = 'App\Models\User';
                
                \Log::info('Found user for billing:', [
                    'email' => $email,
                    'user_id' => $billableId,
                    'customer_id' => $customerId
                ]);
                
                return $this->findOrCreateCustomer(
                    $billableId,
                    $billableType,
                    $customerId,
                );
            } else {
                \Log::warning('User not found for email:', ['email' => $email]);
            }
        }
        
        // الرجوع إلى الطريقة القديمة إذا لم تنجح الطريقة الجديدة
        if (!empty($customerMetadata['billable_id']) && !empty($customerMetadata['billable_type'])) {
            \Log::info('Using legacy metadata approach');
            
            return $this->findOrCreateCustomer(
                $customerMetadata['billable_id'],
                (string) $customerMetadata['billable_type'],
                (string) $customerId,
            );
        }
        
        // إذا فشل كل شيء، رمي استثناء
        throw new InvalidMetadataPayload(
            'Unable to determine billable: missing email/customer_id or legacy metadata. ' .
            'Available data: ' . json_encode([
                'has_customer_id' => !empty($customerId),
                'has_email' => !empty($email),
                'has_legacy_metadata' => !empty($customerMetadata),
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
     * البحث عن قيمة محددة في كامل الـ JSON بشكل ديناميكي
     */
    private function findValueInPayload(array $payload, $targetValue, string $context = '')
    {
        $paths = [];
        
        foreach ($payload as $key => $value) {
            $currentPath = $context ? "$context.$key" : $key;
            
            if (is_array($value)) {
                // البحث المتداخل
                $nestedPaths = $this->findValueInPayload($value, $targetValue, $currentPath);
                $paths = array_merge($paths, $nestedPaths);
            } elseif ($value === $targetValue) {
                $paths[] = $currentPath;
            }
        }
        
        return $paths;
    }

    /**
     * استخراج جميع القيم المهمة من الـ payload
     */
    private function extractImportantValues(array $payload)
    {
        $importantValues = [];
        
        // القيم المطلوبة للبحث عنها
        $targetValues = [
            'db70248c-cf27-4f06-bb6a-f8f4990c3d25', // Customer ID
            'saadmoooooha2000@gmail.com', // Email
            0 // Amount (من الـ JSON المقدم)
        ];
        
        foreach ($targetValues as $value) {
            $paths = $this->findValueInPayload($payload, $value);
            if (!empty($paths)) {
                $importantValues[$value] = [
                    'value' => $value,
                    'paths' => $paths,
                    'type' => $this->determineValueType($value)
                ];
            }
        }
        
        // البحث عن البريد الإلكتروني بنمط
        $emailPaths = $this->findEmailPaths($payload);
        if (!empty($emailPaths)) {
            $importantValues['emails'] = $emailPaths;
        }
        
        return $importantValues;
    }

    /**
     * البحث عن مسارات البريد الإلكتروني
     */
    private function findEmailPaths(array $payload, string $context = '')
    {
        $emailPaths = [];
        
        foreach ($payload as $key => $value) {
            $currentPath = $context ? "$context.$key" : $key;
            
            if (is_array($value)) {
                $nestedPaths = $this->findEmailPaths($value, $currentPath);
                $emailPaths = array_merge($emailPaths, $nestedPaths);
            } elseif (is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $emailPaths[] = [
                    'path' => $currentPath,
                    'email' => $value
                ];
            }
        }
        
        return $emailPaths;
    }

    /**
     * تحديد نوع القيمة
     */
    private function determineValueType($value)
    {
        if (is_string($value)) {
            if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return 'email';
            } elseif (preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89aAbB][a-f0-9]{3}-[a-f0-9]{12}$/', $value)) {
                return 'uuid';
            }
            return 'string';
        } elseif (is_numeric($value)) {
            return 'number';
        }
        return 'unknown';
    }

    /**
     * دالة مساعدة لطباعة المسارات المكتشفة
     */
    private function logDiscoveredPaths(array $payload)
    {
        $importantValues = $this->extractImportantValues($payload);
        
        \Log::info('Discovered paths:', $importantValues);
        
        // طباعة المسارات بشكل مفصل
        foreach ($importantValues as $key => $data) {
            if ($key === 'emails') {
                foreach ($data as $emailData) {
                    \Log::info("Email found at: {$emailData['path']} = {$emailData['email']}");
                }
            } else {
                foreach ($data['paths'] as $path) {
                    \Log::info("{$data['type']} value '{$data['value']}' found at: $path");
                }
            }
        }
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
?>
