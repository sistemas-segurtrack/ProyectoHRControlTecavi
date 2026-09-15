<?php

namespace App\Pwa\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Vite;

/**
 * Sirve `resources/pwa/sw.js` por ruta (así el scope `/pwa/` es válido sin
 * carpeta pública) reemplazando sus placeholders:
 *
 * - `__PWA_BASE__`: prefijo del subpath (ver `config/pwa.php`).
 * - `__PWA_VERSION__`: hash del manifest de Vite. Cambia en cada build, así
 *   que cada deploy entrega un `sw.js` distinto byte a byte: el navegador lo
 *   detecta como versión nueva y la app instalada se actualiza sola.
 * - comentario `__PWA_ASSETS__` + `[]`: JS/CSS de la entrada de la PWA, para precachearlos al
 *   instalar y que la app abra sin señal.
 */
class ServiceWorkerController extends Controller
{
    private const ENTRADA = 'resources/js/pwa/main.ts';

    public function __invoke(): Response
    {
        $manifest = $this->manifest();

        $contenido = strtr((string) file_get_contents(resource_path('pwa/sw.js')), [
            '__PWA_BASE__' => (string) config('pwa.base_path'),
            '__PWA_VERSION__' => $manifest === null ? 'dev' : substr((string) md5_file(public_path('build/manifest.json')), 0, 12),
            '/* __PWA_ASSETS__ */ []' => (string) json_encode($manifest === null ? [] : $this->assets($manifest), JSON_UNESCAPED_SLASHES),
        ]);

        return response($contenido, 200, [
            'Content-Type' => 'application/javascript',
            'Cache-Control' => 'no-cache',
        ]);
    }

    /**
     * Manifest de Vite, o `null` si no hay build (o corre el dev server de Vite).
     *
     * @return array<string, array{file: string, css?: list<string>, imports?: list<string>}>|null
     */
    private function manifest(): ?array
    {
        $ruta = public_path('build/manifest.json');

        if (Vite::isRunningHot() || ! is_file($ruta)) {
            return null;
        }

        $manifest = json_decode((string) file_get_contents($ruta), true);

        return is_array($manifest) ? $manifest : null;
    }

    /**
     * URLs del archivo de la entrada, sus imports (recursivos) y sus CSS.
     *
     * @param  array<string, array{file: string, css?: list<string>, imports?: list<string>}>  $manifest
     * @return list<string>
     */
    private function assets(array $manifest): array
    {
        $archivos = [];
        $pendientes = [self::ENTRADA];
        $visitados = [];

        while ($pendientes !== []) {
            $clave = array_shift($pendientes);

            if (isset($visitados[$clave]) || ! isset($manifest[$clave])) {
                continue;
            }

            $visitados[$clave] = true;
            $chunk = $manifest[$clave];

            $archivos[] = $chunk['file'];
            array_push($archivos, ...($chunk['css'] ?? []));
            array_push($pendientes, ...($chunk['imports'] ?? []));
        }

        return array_map(
            fn (string $archivo): string => asset('build/'.$archivo),
            array_values(array_unique($archivos)),
        );
    }
}
