![](https://banners.beyondco.de/laravel-polar.png?theme=dark&packageManager=composer+require&packageName=danestves%2Flaravel-polar&pattern=pieFactory&style=style_1&description=Easily+integrate+your+Laravel+application+with+Polar.sh&md=1&showWatermark=1&fontSize=100px&images=https%3A%2F%2Flaravel.com%2Fimg%2Flogomark.min.svg "Laravel Polar")

# Laravel Polar

[![Latest Version on Packagist](https://img.shields.io/packagist/v/danestves/laravel-polar.svg?style=flat-square)](https://packagist.org/packages/danestves/laravel-polar)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/danestves/laravel-polar/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/danestves/laravel-polar/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/danestves/laravel-polar/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/danestves/laravel-polar/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/danestves/laravel-polar.svg?style=flat-square)](https://packagist.org/packages/danestves/laravel-polar)

Seamlessly integrate Polar.sh subscriptions and payments into your Laravel application. This package provides an elegant way to handle subscriptions, manage recurring payments, and interact with Polar's API. With built-in support for webhooks, subscription management, and a fluent API, you can focus on building your application while we handle the complexities of subscription billing.

## Installation

You can install the package via composer:

```bash
composer require danestves/laravel-polar
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="laravel-polar-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="laravel-polar-config"
```

This is the contents of the published config file:

> TBD

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="laravel-polar-views"
```

## Usage

> TBD

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
