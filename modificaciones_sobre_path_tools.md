# Modificaciones por el subpath `tools.segurtrack.com/hrcontrol`

Resumen de todo lo que se tocó para que HRControl funcione correctamente
desplegado en un **subpath** (`https://tools.segurtrack.com/hrcontrol`) detrás
de un Apache que ya sirve otras herramientas en el mismo dominio, en vez de un
dominio o subdominio propio. Un subpath no es la forma "por defecto" en la que
Laravel/Vite/Inertia esperan correr, así que aparecieron **cinco** problemas
distintos, cada uno con una causa y un mecanismo de arreglo diferente. Se
documentan en el orden en que se fueron encontrando (varios solo se veían
navegando de verdad, no con `curl`).

---

## 1. `route()` / `redirect()` perdían el prefijo — commit `f6de759`

**Síntoma:** `https://tools.segurtrack.com/hrcontrol` cargaba bien, pero
entrar a login o a la PWA daba el 404 **de Apache** ("Not Found — The
requested URL was not found on this server"), no el de Laravel.

**Causa:** sin forzar la raíz del `UrlGenerator`, cualquier `route('login')` o
`redirect()->route(...)` generado durante una petición real se calculaba con
el host de la petición entrante — que Apache ya manda **sin** el prefijo (el
`ProxyPass` lo recorta antes de reenviar al contenedor). El navegador
terminaba pidiendo `/login` pelado a Apache, que no tiene ese path fuera de
`/hrcontrol`.

**Fix:**
- `app/Providers/AppServiceProvider.php` — `URL::forceRootUrl(config('app.url'))`
  + `URL::forceScheme(...)`.
- `bootstrap/app.php` — `$middleware->trustProxies(at: '*')`.

---

## 2. Fuentes y chunks del bundle de Vite sin prefijo — commit `766cca3`

**Síntoma:** el fix anterior no alcanzó — el usuario pidió "analiza otra vez".
Los logs reales de Apache mostraban un visitante real recibiendo 404 en
`/build/assets/instrument-sans-*.woff*` y en chunks JS como `Welcome-*.js`,
`wayfinder-*.js`, `x-*.js`, todos **sin** `/hrcontrol`.

**Causa:** `URL::forceRootUrl()` solo cubre lo que Laravel genera del lado del
servidor (`route()`, `asset()` vía `ASSET_URL`). Las fuentes referenciadas con
`url()` dentro del CSS compilado y los chunks cargados con `import()` dinámico
(code-splitting de Vue Router/Inertia) usan como raíz el `base` propio de
**Vite**, que nunca se había configurado — eso es una segunda capa,
totalmente independiente de `ASSET_URL`.

**Fix:** `vite.config.ts` — nuevo `base: \`${pwaBasePath}/build/\`` leyendo
`VITE_PWA_BASE_PATH` vía `loadEnv()` de Vite (necesario porque en build-time
es Node, no `import.meta.env`).

---

## 3. La `url` que Inertia comparte con el navegador — commit `40173d0`

**Síntoma:** "ingreso y desaparece el path hrcontrol" — la barra de
direcciones perdía el `/hrcontrol` apenas cargaba cualquier página, aunque el
servidor respondía bien (sin 404, sin errores).

**Causa:** `Inertia\Response::getUrl()` arma la `url` que comparte con cada
página usando `$request->fullUrl()` **directo**, sin pasar por el
`UrlGenerator` — así que ni el fix del punto 1 ni el del punto 2 lo tocaban.
Esa `url` quedaba en `"/"` en vez de `"/hrcontrol/"`, y el cliente de Inertia
hace `history.replaceState(..., page.url)` al hidratar: el navegador
reescribía la URL real perdiendo el prefijo.

**Fix:** `app/Providers/AppServiceProvider.php` —
`Inertia::resolveUrlUsing()` antepone el path de `APP_URL` a la url default,
releyendo `config('app.url')` en cada petición.

De paso se corrigieron los `<link>` de favicon/apple-touch-icon en
`resources/views/app.blade.php` (tenían href fijo en vez de `asset()`).

---

## 4. Botones apuntando a `localhost` + raíz sin sentido — commit `a8c512c`

**Síntoma:** "algunos botones están en localhost" — Log in, Register,
Dashboard, Contactos, Rutas, Settings... navegaban a `http://localhost/...`.
Además la raíz (`/`) mostraba el scaffold "Welcome" de ejemplo de Laravel, no
algo de HRControl.

**Causa (la más seria de las cinco):** `vp build` corre
`php artisan wayfinder:generate` para armar los helpers de ruta de
TypeScript (`login()`, `dashboard()`, etc. en `resources/js/routes/`)
llamando a `route()` del lado del servidor — eso produce una **URL
absoluta** usando el `APP_URL` que vea Laravel en ese momento. El `.env`
descartable que el `Dockerfile` usa solo para compilar (se borra al
terminar) nunca fijaba `APP_URL`, así que caía al default de Laravel
(`http://localhost`) y quedaba grabado dentro del bundle.

**Fix:**
- `Dockerfile` — nuevo `ARG APP_URL`, incluido en el `.env` descartable antes
  de `npm run build`.
- `compose.production.yml` — lo pasa como build arg leyendo el mismo
  `APP_URL` del `.env` real (el que ya usa el contenedor `app` en runtime).
- `routes/web.php` — `Route::redirect('/', '/login')` en vez del scaffold
  "Welcome" (herramienta interna, sin necesidad de landing pública).

---

## 5. Logo del login de la PWA — commit `7dd1bf9`

**Síntoma:** `https://tools.segurtrack.com/recursos/logo-segurtrack.png` no
existe (dominio raíz, de otras herramientas) — el logo no salía en el login
de la PWA.

**Causa:** `resources/js/pwa/pages/LoginPage.vue` tenía
`src="/recursos/logo-segurtrack.png"` fijo. Ese archivo vive en
`public/recursos/` y no pasa por Vite (no se referencia desde ningún
`import`), así que no lo cubre ni `ASSET_URL` ni el `base` de Vite (puntos 1
y 2) — necesitaba el mismo prefijo manual que ya usan `router.ts`/`api.ts`/
`main.ts` de la PWA (`import.meta.env.VITE_PWA_BASE_PATH`).

**Fix:** `logoUrl` computado con ese prefijo en vez de la ruta fija.

---

## El patrón completo (para la próxima vez)

Un despliegue Laravel + Inertia + Vite detrás de un proxy en subpath tiene
**cinco** capas independientes que hay que alinear — ninguna cubre a las
otras:

| # | Qué gobierna | Mecanismo |
|---|---|---|
| 1 | `route()` / `asset()` / redirects del lado del servidor | `URL::forceRootUrl()` + `trustProxies` |
| 2 | Fuentes y chunks horneados dentro del bundle de Vite | `base` en `vite.config.ts` |
| 3 | La `url` que Inertia sincroniza en el navegador (`history.replaceState`) | `Inertia::resolveUrlUsing()` |
| 4 | URLs absolutas generadas en **build-time** (Wayfinder) | `APP_URL` como build-arg de Docker |
| 5 | Assets estáticos referenciados a mano en JS (no vía Vite import ni Blade `asset()`) | Prefijo manual con `VITE_PWA_BASE_PATH` |

Si en el futuro aparece un sexto síntoma parecido, conviene sospechar de otro
punto que lea `$request`/una ruta directo en vez de pasar por el
`UrlGenerator` o el `base` de Vite — y revisar el access log real de Apache,
no solo `curl` propio, porque varios de estos solo se manifestaban con
tráfico de navegador real (chunks dinámicos, `history.replaceState`).

Ver también la memoria `produccion-vps.md` de este proyecto, que tiene el
detalle completo de cada bug con las verificaciones hechas.
