
# Laravel Polar

<p align="center">
<a href="https://github.com/saadmooh/laravel-polar/actions"><img src="https://github.com/saadmooh/laravel-polar/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/saadmooh/laravel-polar"><img src="https://img.shields.io/packagist/dt/saadmooh/laravel-polar" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/saadmooh/laravel-polar"><img src="https://img.shields.io/packagist/v/saadmooh/laravel-polar" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/saadmooh/laravel-polar"><img src="https://img.shields.io/packagist/l/saadmooh/laravel-polar" alt="License"></a>
</p>

> **Note:** English translation is available below the Arabic section.

## 🇸🇦 العربية

### مقدمة

Laravel Polar هي حزمة تدمج تطبيق Laravel الخاص بك مع [Polar.sh](https://polar.sh) بسلاسة، مما يتيح لك إدارة الاشتراكات والفواتير والمدفوعات بسهولة. توفر هذه الحزمة طريقة بسيطة وأنيقة لتنفيذ الميزات القائمة على الاشتراك في تطبيق Laravel الخاص بك.

### التصحيحات على الحزمة الأصلية

هذه الحزمة هي نسخة مصححة من الحزمة الأصلية، حيث تم إجراء التصحيحات التالية:

1. تغيير طريقة HTTP في دالة `updateSubscription` من PATCH إلى POST لتتوافق مع واجهة برمجة تطبيقات Polar الحالية.
2. تحسين دالة `sync()` في نموذج Subscription لدعم كل من `productId` و `product_id` لضمان التوافق مع مختلف تنسيقات البيانات.
3. تصحيح دوال حالة الاشتراك مثل `active()` و `cancelled()` و `pastDue()` لتعمل بشكل صحيح مع القيم المرجعة من واجهة برمجة التطبيقات.
4. معالجة تنسيق البيانات المستلمة من واجهة برمجة التطبيقات لضمان التوافق مع هيكل البيانات المتوقع في التطبيق.

### المميزات

- تكامل سهل مع واجهة برمجة تطبيقات Polar.sh
- إدارة الاشتراكات
- معالجة الويب هوك للتحديثات في الوقت الفعلي
- مكونات Blade للتكامل مع واجهة المستخدم
- أدوات سطر الأوامر لإدارة المنتجات

### المتطلبات

- PHP ^8.3
- Laravel ^10.0 أو ^11.0 أو ^12.0
- Composer

### التثبيت

1. قم بتثبيت الحزمة عبر Composer:

```bash
composer require saadmooh/laravel-polar
```

2. نشر ملفات الإعدادات:

```bash
php artisan vendor:publish --provider="Saadmooh\LaravelPolar\LaravelPolarServiceProvider"
```

3. تشغيل الترحيلات:

```bash
php artisan migrate
```

4. قم بتكوين بيانات اعتماد واجهة برمجة تطبيقات Polar في ملف `.env`:

```
POLAR_API_KEY=your_api_key
POLAR_WEBHOOK_SECRET=your_webhook_secret
```

### الاستخدام الأساسي

#### إعداد اشتراك

```php
use Saadmooh\LaravelPolar\LaravelPolar;

// إنشاء جلسة دفع
$checkout = LaravelPolar::createCheckout([
    'price_id' => 'price_id_from_polar',
    'success_url' => route('subscription.success'),
    'cancel_url' => route('subscription.cancel'),
]);

// إعادة التوجيه إلى صفحة الدفع
return redirect($checkout->url);
```

#### معالجة الويب هوك

تقوم الحزمة تلقائيًا بإعداد معالجة الويب هوك في المسار المحدد في الإعدادات الخاصة بك. تأكد من أن إعدادات الويب هوك في Polar تشير إلى هذا العنوان.

#### استخدام مكونات Blade

```blade
<x-polar::button :price-id="$priceId" :success-url="route('subscription.success')" :cancel-url="route('subscription.cancel')">
    اشترك الآن
</x-polar::button>
```

#### عرض المنتجات عبر واجهة سطر الأوامر

```bash
php artisan polar:products
```

### الإعدادات

بعد نشر ملفات الإعدادات، يمكنك تخصيص سلوك الحزمة في `config/polar.php` ومعالجة الويب هوك في `config/webhook-client.php`.

### التوثيق

للحصول على توثيق أكثر تفصيلاً، يرجى زيارة [التوثيق الرسمي](https://github.com/saadmooh/laravel-polar/wiki).

### المساهمة

المساهمات مرحب بها! لا تتردد في تقديم طلب سحب.

---

## 🇬🇧 English

### Introduction

Laravel Polar is a package that seamlessly integrates your Laravel application with [Polar.sh](https://polar.sh), allowing you to easily manage subscriptions, billing, and payments. This package provides a simple and elegant way to implement subscription-based features in your Laravel application.

### Corrections to the Original Package

This package is a corrected version of the original package, with the following fixes:

1. Changed the HTTP method in the `updateSubscription` function from PATCH to POST to comply with the current Polar API.
2. Improved the `sync()` function in the Subscription model to support both `productId` and `product_id` to ensure compatibility with different data formats.
3. Fixed subscription status functions like `active()`, `cancelled()`, and `pastDue()` to work correctly with the values returned from the API.
4. Handled the formatting of data received from the API to ensure compatibility with the expected data structure in the application.

### Features

- Easy integration with Polar.sh API
- Subscription management
- Webhook handling for real-time updates
- Blade components for UI integration
- Command-line tools for managing products

### Requirements

- PHP ^8.3
- Laravel ^10.0 or ^11.0 or ^12.0
- Composer

### Installation

1. Install the package via Composer:

```bash
composer require saadmooh/laravel-polar
```

2. Publish the configuration files:

```bash
php artisan vendor:publish --provider="Saadmooh\LaravelPolar\LaravelPolarServiceProvider"
```

3. Run the migrations:

```bash
php artisan migrate
```

4. Configure your Polar API credentials in the `.env` file:

```
POLAR_API_KEY=your_api_key
POLAR_WEBHOOK_SECRET=your_webhook_secret
```

### Basic Usage

#### Setting up a subscription

```php
use Saadmooh\LaravelPolar\LaravelPolar;

// Create a checkout session
$checkout = LaravelPolar::createCheckout([
    'price_id' => 'price_id_from_polar',
    'success_url' => route('subscription.success'),
    'cancel_url' => route('subscription.cancel'),
]);

// Redirect to checkout
return redirect($checkout->url);
```

#### Handling webhooks

The package automatically sets up webhook handling at the route defined in your configuration. Make sure your Polar webhook settings point to this URL.

#### Using Blade components

```blade
<x-polar::button :price-id="$priceId" :success-url="route('subscription.success')" :cancel-url="route('subscription.cancel')">
    Subscribe Now
</x-polar::button>
```

#### Listing products via CLI

```bash
php artisan polar:products
```

### Configuration

After publishing the configuration files, you can customize the package behavior in `config/polar.php` and webhook handling in `config/webhook-client.php`.

### Documentation

For more detailed documentation, please visit the [official documentation](https://github.com/saadmooh/laravel-polar/wiki).

### Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

---

## License / الترخيص

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.