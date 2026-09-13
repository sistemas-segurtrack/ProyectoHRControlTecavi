<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Cuenta admin que siembra AdminUserSeeder. El valor de relleno de abajo
    // NO es la contraseña real de ningún entorno — cada entorno (local, VPS)
    // define ADMIN_PASSWORD en su propio .env, nunca en el código.
    'admin' => [
        'email' => env('ADMIN_EMAIL', 'desarrollo@segurtrack.com'),
        'password' => env('ADMIN_PASSWORD', 'cambia-esta-clave'),
    ],

    // Cuenta compartida (rol "usuario") que siembra TecaviUserSeeder: da
    // acceso de solo lectura a /modulos/rutas sin credenciales individuales
    // (el formulario de acceso de esa página solo pide la contraseña, el
    // correo va fijo). Mismo criterio que 'admin' de arriba: el valor de
    // relleno no es la contraseña real de ningún entorno.
    'tecavi' => [
        'email' => env('TECAVI_EMAIL', 'tecavi@segurtrack.com'),
        'password' => env('TECAVI_PASSWORD', 'cambia-esta-clave'),
    ],

    // Aviso por WhatsApp de "hoja de ruta creada/finalizada" (mismo momento
    // que ya avisa por correo, ver App\Listeners\EnviarWhatsapp*). Servicio
    // interno de Segurtrack, sin autenticación propia -- `habilitado` es un
    // apagador de emergencia sin necesitar redeploy si el servicio de
    // WhatsApp da problemas.
    'whatsapp' => [
        'url' => env('WHATSAPP_API_URL', 'https://services.segurtrack.com/api/v1/whatsapp/enviar'),
        'habilitado' => (bool) env('WHATSAPP_HABILITADO', true),
    ],

    'wialon' => [
        'base_url' => env('WIALON_BASE_URL', 'https://hst-api.wialon.com'),
        'token' => trim((string) env('WIALON_STK_TOKEN', '')) ?: null,
        // Recurso Wialon del que salen conductores y carretas (por nombre).
        'resource' => env('WIALON_STK_RESOURCE', 'TECAVI'),
        // Minutos que se cachea el sid obtenido con token/login.
        'sid_ttl' => (int) env('WIALON_SID_TTL', 5),
        // Unidades (por nombre o placa) a las que SÍ se les puede empujar el contador
        // de kilometraje de vuelta a Wialon. Vacío = a ninguna (caso delicado).
        'contador_km_unidades' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('WIALON_CONTADOR_KM_UNIDADES', '')),
        ))),
    ],

];
