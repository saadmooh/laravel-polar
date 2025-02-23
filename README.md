![](https://banners.beyondco.de/laravel-polar.png?theme=dark&packageManager=composer+require&packageName=danestves%2Flaravel-polar&pattern=pieFactory&style=style_1&description=Easily+integrate+your+Laravel+application+with+Polar.sh&md=1&showWatermark=1&fontSize=100px&images=https%3A%2F%2Flaravel.com%2Fimg%2Flogomark.min.svg "Laravel Polar")

# Laravel Polar

[![Latest Version on Packagist](https://img.shields.io/packagist/v/danestves/laravel-polar.svg?style=flat-square)](https://packagist.org/packages/danestves/laravel-polar)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/danestves/laravel-polar/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/danestves/laravel-polar/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/danestves/laravel-polar/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/danestves/laravel-polar/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/danestves/laravel-polar.svg?style=flat-square)](https://packagist.org/packages/danestves/laravel-polar)

Seamlessly integrate Polar.sh subscriptions and payments into your Laravel application. This package provides an elegant way to handle subscriptions, manage recurring payments, and interact with Polar's API. With built-in support for webhooks, subscription management, and a fluent API, you can focus on building your application while we handle the complexities of subscription billing.

## Installation

**Step 1:** You can install the package via composer:

```bash
composer require danestves/laravel-polar
```

**Step 2:** Publish and run the migrations with:

```bash
php artisan vendor:publish --tag="laravel-polar-migrations"
php artisan migrate
```

**Step 3:** You can publish the config file with:

```bash
php artisan vendor:publish --tag="laravel-polar-config"
```

This is the contents of the published config file:

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Polar Access Token
    |--------------------------------------------------------------------------
    |
    | The Polar access token is used to authenticate with the Polar API.
    | You can find your access token in the Polar dashboard > Settings
    | under the "Developers" section.
    |
    */
    'access_token' => env('POLAR_ACCESS_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Polar Webhook Secret
    |--------------------------------------------------------------------------
    |
    | The Polar webhook secret is used to verify that the webhook requests
    | are coming from Polar. You can find your webhook secret in the Polar
    | dashboard > Settings > Webhooks on each registered webhook.
    |
    | We (the developers) recommend using a single webhook for all your
    | integrations. This way you can use the same secret for all your
    | integrations and you don't have to manage multiple webhooks.
    |
    */
    'webhook_secret' => env('POLAR_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Polar Url Path
    |--------------------------------------------------------------------------
    |
    | This is the base URI where routes from Polar will be served
    | from. The URL built into Polar is used by default; however,
    | you can modify this path as you see fit for your application.
    |
    */
    'path' => env('POLAR_PATH', 'polar'),

    /*
    |--------------------------------------------------------------------------
    | Default Redirect URL
    |--------------------------------------------------------------------------
    |
    | This is the default redirect URL that will be used when a customer
    | is redirected back to your application after completing a purchase
    | from a checkout session in your Polar account.
    |
    */
    'redirect_url' => null,

    /*
    |--------------------------------------------------------------------------
    | Currency Locale
    |--------------------------------------------------------------------------
    |
    | This is the default locale in which your money values are formatted in
    | for display. To utilize other locales besides the default "en" locale
    | verify you have to have the "intl" PHP extension installed on the system.
    |
    */
    'currency_locale' => env('POLAR_CURRENCY_LOCALE', 'en'),
];
```

**Step 4:** Optionally, you can publish the views using:

```bash
php artisan vendor:publish --tag="laravel-polar-views"
```

## Usage

### Access Token

Configure your access token. Create a new token in the Polar Dashboard > Settings > Developers and paste it in the `.env` file.

- https://sandbox.polar.sh/dashboard/<org_slug>/settings (Sandbox)
- https://polar.sh/dashboard/<org_slug>/settings (Production)

```bash
POLAR_ACCESS_TOKEN="<your_access_token>"
```

### Webhook Secret

Configure your webhook secret. Create a new webhook in the Polar Dashboard > Settings > Webhooks.

- https://sandbox.polar.sh/dashboard/<org_slug>/settings/webhooks (Sandbox)
- https://polar.sh/dashboard/<org_slug>/settings/webhooks (Production)

Configure the webhook for the following events that this pacckage supports:

- `order.created`
- `order.updated`
- `subscription.created`
- `subscription.updated`
- `subscription.active`
- `subscription.canceled`
- `subscription.revoked`
- `benefit_grant.created`
- `benefit_grant.updated`
- `benefit_grant.revoked`

```bash
POLAR_WEBHOOK_SECRET="<your_webhook_secret>"
```

### Billable Trait

Let’s make sure everything’s ready for your customers to checkout smoothly. 🛒

First, we’ll need to set up a model to handle billing—don’t worry, it’s super simple! In most cases, this will be your app’s User model. Just add the Billable trait to your model like this (you’ll import it from the package first, of course):

```php
use Danestves\LaravelPolar\Billable;

class User extends Authenticatable
{
    use Billable;
}
```

Now the user model will have access to the methods provided by the package. You can make any model billable by adding the trait to it, not just the User model.

### Polar Script

Polar includes a JavaScript script that you can use to initialize the [Polar Embedded Checkout](https://docs.polar.sh/features/checkout/embed). If you going to use this functionality, you can use the `@polar` directive to include the script in your views inside the `<head>` tag.

```blade
<head>
    ...

    @polar
</head>
```

### Webhooks

This package includes a webhook handler that will handle the webhooks from Polar.

#### Webhooks & CSRF Protection

Incoming webhooks should not be affected by [CSRF protection](https://laravel.com/docs/csrf). To prevent this, add your webhook path to the except list of your `App\Http\Middleware\VerifyCsrfToken` middleware:

```php
protected $except = [
    'polar/*',
];
```

Or if you're using Laravel v11 and up, you should exclude `polar/*` in your application's `bootstrap/app.php` file:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        'polar/*',
    ]);
})
```

### Commands

This package includes a list of commands that you can use to retrieve information about your Polar account.

| Command | Description |
|---------|-------------|
| `php artisan polar:products` | List all available products with their ids |

### Checkouts

#### Single Payments

To create a checkout to show only a single payment, pass a single items to the array of products when creating the checkout.

```php
use Illuminate\Http\Request;

Route::get('/subscribe', function (Request $request) {
    return $request->user()->checkout(['product_id_123']);
});
```

If you want to show multiple products that the user can choose from, you can pass an array of product ids to the `checkout` method.

```php
use Illuminate\Http\Request;

Route::get('/subscribe', function (Request $request) {
    return $request->user()->checkout(['product_id_123', 'product_id_456']);
});
```

This could be useful if you want to offer monthly, yearly, and lifetime plans for example.

> [!NOTE]
> If you are requesting the checkout a lot of times we recommend you to cache the URL returned by the `checkout` method.

#### Custom Price

You can override the price of a product using the `charge` method.

```php
use Illuminate\Http\Request;

Route::get('/subscribe', function (Request $request) {
    return $request->user()->charge(1000, ['product_id_123']);
});
```

#### Embedded Checkout

Instead of redirecting the user you can create the checkout link, pass it to the page and use our blade component:

```php
use Illuminate\Http\Request;

Route::get('/billing', function (Request $request) {
    $checkout = $request->user()->checkout(['product_id_123']);

    return view('billing', ['checkout' => $checkout]);
});
```

Now we can use the button like this:

```blade
<x-polar-button :checkout="$checkout" />
```

The component accepts the normal props that a link element accepts. You can change the theme of the embedded checkout by using the following prop:

```blade
<x-polar-button :checkout="$checkout" data-polar-checkout-theme="dark" />
```

It defaults to light theme, so you only need to pass the prop if you want to change it.

### Prefill Customer Information

You can override the user data using the following methods in your models provided by the `Billable` trait.

```php
public function polarName(): ?string; // default: $model->name
public function polarEmail(): ?string; // default: $model->email
```

### Redirects After Purchase

You can redirect the user to a custom page after the purchase using the `withSuccessUrl` method:

```php
$request->user()->checkout('variant-id')
    ->withSuccessUrl(url('/success'));
```

You can also add the `checkout_id={CHECKOUT_ID}` query parameter to the URL to retrieve the checkout session id:

```php
$request->user()->checkout('variant-id')
    ->withSuccessUrl(url('/success?checkout_id={CHECKOUT_ID}'));
```

### Custom metadata and customer metadata

You can add custom metadata to the checkout session using the `withMetadata` method:

```php
$request->user()->checkout('variant-id')
    ->withMetadata(['key' => 'value']);
```

You can also add customer metadata to the checkout session using the `withCustomerMetadata` method:

```php
$request->user()->checkout('variant-id')
    ->withCustomerMetadata(['key' => 'value']);
```

These will then be available in the relevant webhooks for you.

#### Reserved Keywords

When working with custom data, this library has a few reserved terms.

- `billable_id`
- `billable_type`
- `subscription_type`

Using any of these will result in an exception being thrown.

### Customers

#### Customer Portal

Customers can update their personal information (e.g., name, email address) by accessing their [self-service customer portal](https://docs.polar.sh/features/customer-portal). To redirect customers to this portal, call the `redirectToCustomerPortal()` method on your billable model (e.g., the User model).

```php
use Illuminate\Http\Request;

Route::get('/customer-portal', function (Request $request) {
    return $request->user()->redirectToCustomerPortal();
});
```

Optionally, you can obtain the signed customer portal URL directly:

```php
$url = $user->customerPortalUrl();
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [laravel/cashier (Stripe)](https://github.com/laravel/cashier-stripe)
- [laravel/cashier (Paddle)](https://github.com/laravel/cashier-paddle)
- [lemonsqueezy/laravel](https://github.com/lmsqueezy/laravel)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
