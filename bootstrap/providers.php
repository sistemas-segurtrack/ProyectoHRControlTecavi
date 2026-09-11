<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Services\Wialon\WialonServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    WialonServiceProvider::class,
];
