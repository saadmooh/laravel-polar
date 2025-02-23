<?php

namespace Danestves\LaravelPolar\Concerns;

use Danestves\LaravelPolar\Checkout;
use Danestves\LaravelPolar\Exceptions\ReservedMetadataKeys;
use Polar\Models\Components;

trait ManagesCheckouts // @phpstan-ignore-line trait.unused - ManagesCheckouts is used in Billable trait
{
    /**
     * Create a new checkout instance to sell a product with a custom price.
     *
     * @param  array<string>  $products
     * @param  array<string, string|int>|null  $options
     * @param  array<string, string|int|bool>|null  $metadata
     */
    public function charge(int $amount, array $products, ?array $options = [], ?array $metadata = []): Checkout
    {
        return $this->checkout($products, array_merge($options, [
            'amount' => $amount,
        ]), $metadata);
    }

    /**
     * Create a new checkout instance to sell a product.
     *
     * @param  array<string>  $products
     * @param  array<string, string|int>|null  $options
     * @param  array<string, string|int|bool>|null  $metadata
     */
    public function checkout(array $products, ?array $options = [], ?array $metadata = []): Checkout
    {
        if (
            (array_key_exists('billable_id', $metadata) && isset($metadata['billable_id'])) ||
            (array_key_exists('billable_type', $metadata) && isset($metadata['billable_type'])) ||
            (array_key_exists('subscription_type', $metadata) && isset($metadata['subscription_type']))
        ) {
            throw ReservedMetadataKeys::overwriteAttempt();
        }

        /** @var string|int $key */
        $key = $this->getKey();

        $metadata = array_merge($metadata, [
            'billable_id' => (string) $key,
            'billable_type' => $this->getMorphClass(),
        ]);

        /** @var Components\Address|null */
        $billingAddress = null;
        if (isset($options['country'])) {
            $billingAddress = new Components\Address(
                country: (string) $options['country'],
                line1: isset($options['line1']) ? (string) $options['line1'] : null,
                line2: isset($options['line2']) ? (string) $options['line2'] : null,
                postalCode: isset($options['zip']) ? (string) $options['zip'] : null,
                city: isset($options['city']) ? (string) $options['city'] : null,
                state: isset($options['state']) ? (string) $options['state'] : null,
            );
        }

        $checkout = Checkout::make($products)
            ->withCustomerName((string) ($options['customer_name'] ?? $this->polarName() ?? ''))
            ->withCustomerEmail((string) ($options['customer_email'] ?? $this->polarEmail() ?? ''))
            ->withCustomerBillingAddress($billingAddress)
            ->withMetadata($metadata);

        if (isset($options['tax_id'])) {
            $checkout->withCustomerTaxId((string) $options['tax_id']);
        }

        if (isset($options['discount_id'])) {
            $checkout->withDiscountId((string) $options['discount_id']);
        }

        if (isset($options['amount']) && is_numeric($options['amount'])) {
            $checkout->withAmount((int) $options['amount']);
        }

        return $checkout;
    }
}
