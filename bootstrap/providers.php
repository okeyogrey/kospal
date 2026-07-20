<?php

use App\Providers\AppServiceProvider;
use App\Providers\DeploymentServiceProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    DeploymentServiceProvider::class,
    FortifyServiceProvider::class,
];
