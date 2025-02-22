<?php

namespace Danestves\LaravelPolar\Database\Factories;

use Danestves\LaravelPolar\Customer;
use Danestves\LaravelPolar\Enums\OrderStatus;
use Danestves\LaravelPolar\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'billable_id' => rand(1, 1000),
            'billable_type' => 'App\\Models\\User',
            'polar_id' => $this->faker->uuid,
            'status' => OrderStatus::Paid,
            'amount' => rand(50, 1000),
            'tax_amount' => rand(1, 50),
            'refunded_amount' => rand(50, 1000),
            'refunded_tax_amount' => rand(1, 50),
            'currency' => 'USD',
            'billing_reason' => $this->faker->randomElement(['purchase', 'subscription_create', 'subscription_cycle', 'subscription_update']),
            'customer_id' => $this->faker->uuid,
            'product_id' => $this->faker->uuid,
            'product_price_id' => $this->faker->uuid,
            'refunded_at' => null,
            'ordered_at' => now(),
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): self
    {
        return $this->afterCreating(function ($subscription) {
            Customer::factory()->create([
                'billable_id' => $subscription->billable_id,
                'billable_type' => $subscription->billable_type,
            ]);
        });
    }

    /**
     * Mark the order as paid.
     */
    public function paid(): self
    {
        return $this->state([
            'status' => OrderStatus::Paid,
        ]);
    }

    /**
     * Mark the order as refunded.
     */
    public function refunded(): self
    {
        return $this->state([
            'status' => OrderStatus::Refunded,
        ]);
    }

    /**
     * Mark the order as partially refunded.
     */
    public function partiallyRefunded(): self
    {
        return $this->state([
            'status' => OrderStatus::PartiallyRefunded,
        ]);
    }
}
