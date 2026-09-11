<?php

test('el manifest de la pwa tiene start_url dentro de scope', function () {
    $response = $this->get(route('pwa.manifest'))->assertOk();

    $manifest = $response->json();

    // El algoritmo "within scope" del spec de Web App Manifest compara
    // start_url y scope como strings con el mismo origen — si start_url no
    // empieza exactamente con scope (barra final incluida), Chrome ignora el
    // scope declarado por completo y cae a un scope mucho más amplio que el
    // pretendido (ver comentario en routes/pwa.php). Confirmado con Chrome
    // real vía CDP Page.getAppManifest.
    expect($manifest['start_url'])->toStartWith($manifest['scope']);
});

test('el manifest de la pwa sin subpath configurado usa rutas relativas simples', function () {
    config(['pwa.base_path' => '']);

    $manifest = $this->get(route('pwa.manifest'))->assertOk()->json();

    expect($manifest['start_url'])->toBe('/pwa/');
    expect($manifest['scope'])->toBe('/pwa/');
});

test('el manifest de la pwa respeta el prefijo del subpath', function () {
    config(['pwa.base_path' => '/hrcontrol']);

    $manifest = $this->get(route('pwa.manifest'))->assertOk()->json();

    expect($manifest['start_url'])->toBe('/hrcontrol/pwa/');
    expect($manifest['scope'])->toBe('/hrcontrol/pwa/');
});
