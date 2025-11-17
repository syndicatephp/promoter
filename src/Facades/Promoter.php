<?php

namespace Syndicate\Promoter\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Syndicate\Promoter\Promoter
 */
class Promoter extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Syndicate\Promoter\Promoter::class;
    }
}
