<?php

namespace Danestves\LaravelPolar\Enums;

enum OrderStatus: string
{
    case Paid = 'paid';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';
}
