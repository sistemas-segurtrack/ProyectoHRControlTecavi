<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Todos los catálogos (conductores, unidades + contador de kilometraje,
// carretas, geocercas) cada minuto. Antes solo "unidades" corría cada minuto
// (el odómetro cambia al circular) y el resto cada hora; al pasar TODO a
// cada minuto, ese sync completo ya cubre unidades, así que el comando
// separado de arriba quedaba duplicado y se quitó.
Schedule::command('wialon:sync')->everyMinute()->withoutOverlapping();
