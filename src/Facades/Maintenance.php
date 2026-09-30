<?php

namespace Aesis\Maintenance\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Aesis\Maintenance\Maintenance
 */
class Maintenance extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Aesis\Maintenance\Maintenance::class;
    }
}
