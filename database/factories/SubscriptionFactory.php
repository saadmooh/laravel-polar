<?php

namespace Danestves\LaravelPolar\Database\Factories;

use Danestves\LaravelPolar\Customer;
use Danestves\LaravelPolar\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Polar\Models\Components;

class SubscriptionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Subscription::class;

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
            'type' => 'default',
            'polar_id' => $this->faker->uuid,
            'status' => Components\SubscriptionStatus::Active,
            'product_id' => $this->faker->uuid,
            'price_id' => $this->faker->uuid,
            'current_period_end' => now()->addDays(30),
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
     * Mark the subscription as active.
     */
    public function active(): self
    {
        return $this->state([
            'status' => Components\SubscriptionStatus::Active,
        ]);
    }

    /**
     * Mark the subscription as past due.
     */
    public function pastDue(): self
    {
        return $this->state([
            'status' => Components\SubscriptionStatus::PastDue,
        ]);
    }

    /**
     * Mark the subscription as unpaid.
     */
    public function unpaid(): self
    {
        return $this->state([
            'status' => Components\SubscriptionStatus::Unpaid,
        ]);
    }

    /**
     * Mark the subscription as cancelled.
     */
    public function cancelled(): self
    {
        return $this->state([
            'status' => Components\SubscriptionStatus::Canceled,
            'ends_at' => now(),
        ]);
    }

    /**
     * Mark the subscription as expired
     */
    public function incompleteExpired(): self
    {
        return $this->state([
            'status' => Components\SubscriptionStatus::IncompleteExpired,
        ]);
    }
}
