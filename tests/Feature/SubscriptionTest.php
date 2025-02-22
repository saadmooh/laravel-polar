<?php

use Danestves\LaravelPolar\Subscription;

it('can determine if the subscription is valid while on its grace period', function () {
    $subscription = Subscription::factory()->cancelled()->create([
        'ends_at' => now()->addDays(5),
    ]);

    expect($subscription->valid())->toBeTrue();

    $subscription = Subscription::factory()->cancelled()->create([
        'ends_at' => now()->subDays(5),
    ]);

    expect($subscription->valid())->toBeFalse();
});
