<?php

namespace Danestves\LaravelPolar\Exceptions;

use Exception;

class ReservedMetadataKeys extends Exception
{
    public static function overwriteAttempt(): ReservedMetadataKeys
    {
        return new ReservedMetadataKeys(
            'You cannot use "billable_id", "billable_type" or "subscription_type" as custom data keys because these are reserved keywords.',
        );
    }
}
