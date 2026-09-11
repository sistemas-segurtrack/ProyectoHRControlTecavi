<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Prefijo de la PWA Conductor
    |--------------------------------------------------------------------------
    |
    | Vacío en desarrollo y cuando la app va en la raíz de su dominio. Si en
    | producción se sirve detrás de un proxy en subpath (por ejemplo
    | tools.segurtrack.com/hrcontrol), poner aquí ese prefijo ("/hrcontrol")
    | para que el manifest y el service worker generen rutas correctas.
    |
    */

    'base_path' => rtrim((string) env('PWA_BASE_PATH', ''), '/'),

];
