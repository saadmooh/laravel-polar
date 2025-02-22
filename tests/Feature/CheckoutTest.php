<?php

use Danestves\LaravelPolar\Checkout;
use Illuminate\Http\RedirectResponse;
use Mockery;

it('can initiate a new checkout', function () {
    $checkoutUrl = 'https://sandbox.polar.sh/checkout/polar_c_123';

    $checkout = Mockery::mock(Checkout::class);
    $checkout->shouldReceive('url')
        ->once()
        ->andReturn($checkoutUrl);

    expect($checkout)->toBeInstanceOf(Checkout::class);
    expect($checkout->url())->toBe($checkoutUrl);
});

it('can be redirected', function () {
    $checkoutUrl = 'https://sandbox.polar.sh/checkout/polar_c_123';

    $checkout = Mockery::mock(Checkout::class);
    $checkout->shouldReceive('redirect')
        ->twice()
        ->andReturn(new RedirectResponse($checkoutUrl));

    expect($checkout->redirect())->toBeInstanceOf(RedirectResponse::class);
    expect($checkout->redirect()->getTargetUrl())->toBe($checkoutUrl);
});

it('can set prefilled fields with dedicated methods', function () {
    $checkout = Mockery::mock(Checkout::class);

    $checkout->shouldReceive('withCustomerName')
        ->once()
        ->with('John Doe');

    $checkout->shouldReceive('withCustomerEmail')
        ->once()
        ->with('john@doe.com');

    $checkout->shouldReceive('url')
        ->once()
        ->andReturn('https://sandbox.polar.sh/checkout/polar_c_123');

    $checkout->withCustomerName('John Doe');
    $checkout->withCustomerEmail('john@doe.com');

    expect($checkout->url())->toBe('https://sandbox.polar.sh/checkout/polar_c_123');
});

it('can include metadata', function () {
    $checkout = Mockery::mock(Checkout::class);

    $checkout->shouldReceive('withMetadata')
        ->once()
        ->with(['batch_id' => '789']);

    $checkout->shouldReceive('url')
        ->once()
        ->andReturn('https://sandbox.polar.sh/checkout/polar_c_123');

    $checkout->withMetadata(['batch_id' => '789']);

    expect($checkout->url())->toBe('https://sandbox.polar.sh/checkout/polar_c_123');
});

it('can include prefilled fields and metadata', function () {
    $checkout = Mockery::mock(Checkout::class);

    $checkout->shouldReceive('withCustomerName')
        ->once()
        ->with('John Doe');

    $checkout->shouldReceive('withMetadata')
        ->once()
        ->with(['batch_id' => '789']);

    $checkout->shouldReceive('url')
        ->once()
        ->andReturn('https://sandbox.polar.sh/checkout/polar_c_123');

    $checkout->withCustomerName('John Doe');
    $checkout->withMetadata(['batch_id' => '789']);

    expect($checkout->url())->toBe('https://sandbox.polar.sh/checkout/polar_c_123');
});
