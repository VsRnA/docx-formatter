<?php

use App\Infrastructure\Providers\DomainServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    HorizonServiceProvider::class,
    DomainServiceProvider::class,
];
