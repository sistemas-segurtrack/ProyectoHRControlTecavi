<?php

namespace App\Pwa\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deduplica POSTs reintentados desde la cola offline de la PWA. El cliente envía
 * `Idempotency-Key: <uuid>`; la primera respuesta 2xx se cachea 24 h y se
 * reproduce en los reintentos.
 */
class EnsureIdempotent
{
    private const TTL_HORAS = 24;

    public function handle(Request $request, Closure $next): Response
    {
        $key = trim((string) $request->header('Idempotency-Key', ''));

        if ($key === '' || ! $request->isMethod('POST')) {
            return $next($request);
        }

        $cacheKey = 'pwa:idem:'.sha1($key);

        /** @var array{status:int, body:mixed}|null $guardado */
        $guardado = Cache::get($cacheKey);

        if ($guardado !== null) {
            return response()
                ->json($guardado['body'], $guardado['status'])
                ->header('Idempotency-Replayed', 'true');
        }

        $response = $next($request);

        if ($response instanceof Response && $response->isSuccessful()) {
            Cache::put($cacheKey, [
                'status' => $response->getStatusCode(),
                'body' => json_decode((string) $response->getContent(), true),
            ], now()->addHours(self::TTL_HORAS));
        }

        return $response;
    }
}
