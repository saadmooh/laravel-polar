<?php

namespace Danestves\LaravelPolar;

use Danestves\LaravelPolar\Concerns\ManagesCheckouts;
use Danestves\LaravelPolar\Concerns\ManagesCustomer;
use Danestves\LaravelPolar\Concerns\ManagesOrders;
use Danestves\LaravelPolar\Concerns\ManagesSubscription;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait Billable
{
    use ManagesCheckouts;
    use ManagesCustomer;
    use ManagesOrders;
    use ManagesSubscription;
}
