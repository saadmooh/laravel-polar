<?php

namespace Danestves\LaravelPolar\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WebhookHandled
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        /**
         * The payload array.
         *
         * @var array<string, mixed>
         */
        public array $payload,
    ) {}
}
