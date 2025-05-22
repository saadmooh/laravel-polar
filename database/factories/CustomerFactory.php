<?php

namespace Danestves\LaravelPolar\Database\Factories;

use Danestves\LaravelPolar\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Customer>
     */
    protected $model = Customer::class;

    /**
     * Define the model's default state.
     *
     * @return array{
     *     billable_id: int,
     *     billable_type: string,
     *     polar_id: string,
     *     trial_ends_at: \Carbon\CarbonInterface|null,
     * }
     */
    public function definition(): array
    {
        return [
            'billable_id' => $this->faker->randomNumber(),
            'billable_type' => 'App\\Models\\User',
            'polar_id' => $this->faker->uuid,
            'trial_ends_at' => null,
        ];
    }
}
