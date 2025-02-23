<?php

it('can render a button', function () {
    $view = $this->blade(
        '<x-polar-button :href="$href">Buy Now</x-polar-button>',
        ['href' => 'https://sandbox.polar.sh/checkout/buy/variant_123'],
    );

    $view->assertSee('<a href="https://sandbox.polar.sh/checkout/buy/variant_123" data-polar-checkout data-polar-checkout-theme="light" >', false)
        ->assertSee('Buy Now', false)
        ->assertSee('</a>', false);
});
