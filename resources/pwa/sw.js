/* Service Worker — PWA Conductor Tecavi.
   App-shell cacheado + assets inmutables. La API nunca se cachea aquí
   (la cola offline se maneja en la app, Fase 4). */

const CACHE = 'tecavi-pwa-v2';
// El servidor reemplaza este placeholder por el prefijo real (vacío, o algo
// como "/hrcontrol" si la app va detrás de un proxy en subpath) al servir
// este archivo — ver routes/pwa.php.
const BASE = '__PWA_BASE__';
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

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(CACHE)
            .then((c) => c.add(SHELL_URL))
            .catch(() => {}),
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
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

    // Navegacion (abrir la app): red primero, si falla usa el shell cacheado y luego la pagina offline.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((res) => {
                    caches
                        .open(CACHE)
                        .then((c) => c.put(SHELL_URL, res.clone()));
                    return res;
                })
                .catch(() =>
                    caches.match(SHELL_URL).then(
                        (cached) =>
                            cached ??
                            new Response(OFFLINE_HTML, {
                                headers: {
                                    'Content-Type': 'text/html; charset=utf-8',
                                },
                            }),
                    ),
                ),
        );
        return;
    }

    // Assets del build de Vite: cache primero. El nombre de archivo lleva un
    // hash del contenido (p. ej. app-B9f7vUcm.js), así que nunca queda un
    // cache desactualizado bajo el mismo nombre — un cambio real siempre
    // pide un archivo distinto.
    if (
        url.pathname.startsWith(`${BASE}/build/`) ||
        /\.(?:js|css|woff2?)$/.test(url.pathname)
    ) {
        event.respondWith(
            caches.match(request).then(
                (cached) =>
                    cached ??
                    fetch(request).then((res) => {
                        if (res.ok) {
                            const copia = res.clone();
                            caches
                                .open(CACHE)
                                .then((c) => c.put(request, copia));
                        }
                        return res;
                    }),
            ),
        );
        return;
    }

    // Manifest e íconos/logos bajo /recursos/: el NOMBRE de archivo no
    // cambia aunque cambie el contenido (nuevo logo, nombre de la app...),
    // así que "cache primero" los dejaría pegados para siempre. Cache-y-
    // -revalida: responde al toque con lo cacheado si existe (rápido,
    // funciona offline), pero siempre dispara un fetch en segundo plano que
    // actualiza el cache para la próxima vez.
    if (
        url.pathname.startsWith(`${BASE}/recursos/`) ||
        url.pathname.endsWith('.webmanifest')
    ) {
        event.respondWith(
            caches.match(request).then((cached) => {
                const actualizado = fetch(request)
                    .then((res) => {
                        if (res.ok) {
                            caches
                                .open(CACHE)
                                .then((c) => c.put(request, res.clone()));
                        }
                        return res;
                    })
                    .catch(() => cached);

                return cached ?? actualizado;
            }),
        );
    }
});
