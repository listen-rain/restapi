<?php

namespace Listen\Restapi\Facades;

use Illuminate\Support\Facades\Facade;

class Restapi extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'restapi';
    }
}
