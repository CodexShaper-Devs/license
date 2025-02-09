<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class LicenseManager extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'license-manager';
    }
}