<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Unidades/placas + contador de kilometraje: cada minuto (el odómetro cambia al circular).
Schedule::command('wialon:sync --solo=unidades')->everyMinute()->withoutOverlapping();

// Resto de catálogos (conductores, carretas, geocercas): cada hora.
Schedule::command('wialon:sync')->hourly()->withoutOverlapping();
