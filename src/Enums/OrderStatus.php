<?php

namespace Danestves\LaravelPolar\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';
}
