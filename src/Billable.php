<?php

namespace Danestves\LaravelPolar;

use Danestves\LaravelPolar\Concerns\ManagesCheckouts;
use Danestves\LaravelPolar\Concerns\ManagesCustomer;
use Danestves\LaravelPolar\Concerns\ManagesOrders;
use Danestves\LaravelPolar\Concerns\ManagesSubscription;

trait Billable // @phpstan-ignore-line trait.unused - Billable is used in the user final code
{
    use ManagesCheckouts;
    use ManagesCustomer;
    use ManagesOrders;
    use ManagesSubscription;
}
