/* Service Worker — PWA Conductor Tecavi.
   App-shell + assets del build cacheados. La API nunca se cachea aquí (la
   cola offline se maneja en la app: resources/js/pwa/lib/outbox.ts).

   El servidor reemplaza los placeholders al servir este archivo (ver
   App\Pwa\Controllers\ServiceWorkerController): el prefijo del subpath, la
   versión del build y la lista de assets a precachear. Como la versión
   cambia en cada deploy, el navegador ve un sw.js nuevo, lo instala y la
   app se actualiza sola (resources/js/pwa/lib/actualizaciones.ts). */

const BASE = '__PWA_BASE__';
const VERSION = '__PWA_VERSION__';
// El servidor reemplaza el comentario + `[]` por la lista JSON de URLs.
const ASSETS = /* __PWA_ASSETS__ */ [];

const CACHE = `tecavi-pwa-${VERSION}`;
const SHELL_URL = `${BASE}/pwa`;

const OFFLINE_HTML = `<!doctype html><html lang="es"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sin conexión</title>
<style>body{font-family:system-ui,sans-serif;background:#f9fafb;color:#111827;
display:flex;min-height:100vh;margin:0;align-items:center;justify-content:center;text-align:center;padding:2rem}
.b{width:56px;height:56px;border-radius:1rem;background:#b51927;color:#fff;
display:flex;align-items:center;justify-content:center;margin:0 auto 1rem}</style></head>
<body><div><div class="b"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 13l4 4L19 7" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg></div><h1>Sin conexión</h1>
<p>Abre la app cuando tengas señal. Tus datos guardados se enviarán solos.</p>
<button onclick="location.reload()" style="margin-top:1rem;padding:.75rem 1.5rem;border:0;border-radius:.75rem;background:#b51927;color:#fff;font-weight:700">Reintentar</button>
</div></body></html>`;

/**
 * Guarda una copia de la respuesta (solo si es 2xx) y devuelve la original.
 * La copia se saca ANTES de entregar la respuesta: después su cuerpo ya
 * estaría consumido y `clone()` fallaría.
 */
function guardar(clave, res) {
    if (res.ok) {
        const copia = res.clone();
        caches
            .open(CACHE)
            .then((c) => c.put(clave, copia))
            .catch(() => {
                /* cuota llena o cache no disponible: se sirve igual */
            });
    }
    return res;
}

function shellOffline() {
    return caches.match(SHELL_URL).then(
        (cached) =>
            cached ??
            new Response(OFFLINE_HTML, {
                headers: { 'Content-Type': 'text/html; charset=utf-8' },
            }),
    );
}

self.addEventListener('install', (event) => {
    // allSettled: un asset que falle no debe impedir que la versión nueva se instale.
    event.waitUntil(
        caches
            .open(CACHE)
            .then((c) =>
                Promise.allSettled([SHELL_URL, ...ASSETS].map((u) => c.add(u))),
            ),
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    // Borra las caches de versiones anteriores (sus assets ya no se usan).
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((k) => k !== CACHE)
                        .map((k) => caches.delete(k)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    // La API va siempre a la red.
    if (url.pathname.startsWith(`${BASE}/api/`)) return;

    // Navegación (abrir la app): red primero. Sin señal, o si el servidor
    // responde 5xx (p. ej. durante un deploy), usa el shell cacheado.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((res) =>
                    res.status >= 500
                        ? caches.match(SHELL_URL).then((c) => c ?? res)
                        : guardar(SHELL_URL, res),
                )
                .catch(shellOffline),
        );
        return;
    }

    // Assets del build de Vite: cache primero. El nombre lleva un hash del
    // contenido, así que un cambio real siempre pide un archivo distinto.
    if (
        url.pathname.startsWith(`${BASE}/build/`) ||
        /\.(?:js|css|woff2?)$/.test(url.pathname)
    ) {
        event.respondWith(
            caches
                .match(request)
                .then(
                    (cached) =>
                        cached ??
                        fetch(request).then((res) => guardar(request, res)),
                ),
        );
        return;
    }

    // Manifest e íconos bajo /recursos/: el nombre no cambia aunque cambie
    // el contenido, así que se responde con lo cacheado (rápido, funciona
    // offline) y se revalida en segundo plano para la próxima vez.
    if (
        url.pathname.startsWith(`${BASE}/recursos/`) ||
        url.pathname.endsWith('.webmanifest')
    ) {
        event.respondWith(
            caches.match(request).then((cached) => {
                const actualizado = fetch(request)
                    .then((res) => guardar(request, res))
                    .catch(() => cached ?? Response.error());

                return cached ?? actualizado;
            }),
        );
    }
});
