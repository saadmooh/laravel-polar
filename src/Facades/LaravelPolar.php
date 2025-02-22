<?php

namespace Danestves\LaravelPolar\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Danestves\LaravelPolar\LaravelPolar
 */
class LaravelPolar extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Danestves\LaravelPolar\LaravelPolar::class;
    }
}
