<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\InstallServiceProvider;

return [
    InstallServiceProvider::class,
    AppServiceProvider::class,
    FortifyServiceProvider::class,
];
