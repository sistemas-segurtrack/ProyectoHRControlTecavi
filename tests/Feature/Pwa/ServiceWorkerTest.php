<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->publico = storage_path('framework/testing/sw-public');
    File::ensureDirectoryExists($this->publico.'/build');
    $this->app->usePublicPath($this->publico);
});

afterEach(function () {
    File::deleteDirectory($this->publico);
});

function escribirManifest(string $publico, array $manifest): void
{
    file_put_contents($publico.'/build/manifest.json', json_encode($manifest));
}

test('sw.js lleva la versión del build y precachea el JS y CSS de la entrada de la PWA', function () {
    escribirManifest($this->publico, [
        'resources/js/pwa/main.ts' => [
            'file' => 'assets/main-A1.js',
            'css' => ['assets/app-C1.css'],
            'imports' => ['_x-B1.js', '_vue-D1.js'],
        ],
        '_x-B1.js' => ['file' => 'assets/x-B1.js', 'imports' => ['_vue-D1.js']],
        '_vue-D1.js' => ['file' => 'assets/vue-D1.js'],
        'resources/js/app.ts' => ['file' => 'assets/admin-Z9.js'],
    ]);
    $version = substr(md5_file($this->publico.'/build/manifest.json'), 0, 12);

    $contenido = $this->get('/pwa/sw.js')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/javascript')
        ->getContent();

    expect($contenido)
        ->not->toContain('__PWA_')
        ->toContain("const VERSION = '{$version}';")
        ->toContain(asset('build/assets/main-A1.js'))
        ->toContain(asset('build/assets/app-C1.css'))
        ->toContain(asset('build/assets/x-B1.js'))
        ->toContain(asset('build/assets/vue-D1.js'))
        ->not->toContain('admin-Z9.js');
    expect(substr_count($contenido, 'vue-D1.js'))->toBe(1);
});

test('un build nuevo cambia el contenido de sw.js (así la app instalada se actualiza)', function () {
    escribirManifest($this->publico, ['resources/js/pwa/main.ts' => ['file' => 'assets/main-A1.js']]);
    $antes = $this->get('/pwa/sw.js')->getContent();

    escribirManifest($this->publico, ['resources/js/pwa/main.ts' => ['file' => 'assets/main-A2.js']]);
    $despues = $this->get('/pwa/sw.js')->getContent();

    expect($despues)->not->toBe($antes);
});

test('sin build de Vite sw.js usa la versión dev y no precachea assets', function () {
    $this->get('/pwa/sw.js')
        ->assertOk()
        ->assertSee("const VERSION = 'dev';", false)
        ->assertSee('const ASSETS = [];', false);
});
