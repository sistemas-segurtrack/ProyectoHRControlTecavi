import { ref } from 'vue';
import type { Router } from 'vue-router';

/**
 * Hay una versión nueva ya activa. Se aplica sola (recarga) salvo en un
 * formulario, donde se espera a que el conductor salga para no perder lo que
 * está llenando; mientras tanto `App.vue` muestra "Actualizar ahora".
 */
export const hayActualizacion = ref(false);

/** Pantallas con datos a medio llenar: no se recargan solas. */
const RUTAS_CON_FORMULARIO = new Set(['nueva-ruta', 'continuar']);

/**
 * Cada deploy entrega un `sw.js` distinto (ver `ServiceWorkerController`): el
 * navegador lo instala y, como hace `skipWaiting()` + `clients.claim()`, pasa
 * a controlar la página y dispara `controllerchange`. Se revisa `sw.js` al
 * volver a la app, al recuperar señal y cada 5 minutos (el navegador por su
 * cuenta puede tardar hasta 24h).
 */
export function vigilarActualizaciones(
    registro: ServiceWorkerRegistration,
    router: Router,
): void {
    // El primer `controllerchange` de una página sin SW es la instalación
    // inicial, no una actualización.
    let controlador = navigator.serviceWorker.controller;

    navigator.serviceWorker.addEventListener('controllerchange', () => {
        const eraActualizacion = controlador !== null;
        controlador = navigator.serviceWorker.controller;
        if (!eraActualizacion) return;

        hayActualizacion.value = true;
        aplicarSiNoHayFormulario(router);
    });

    router.afterEach(() => {
        if (hayActualizacion.value) aplicarSiNoHayFormulario(router);
    });

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') void registro.update();
    });
    window.addEventListener('online', () => void registro.update());
    setInterval(() => void registro.update(), 5 * 60 * 1000);
}

function aplicarSiNoHayFormulario(router: Router): void {
    const nombre = router.currentRoute.value.name;
    if (typeof nombre === 'string' && RUTAS_CON_FORMULARIO.has(nombre)) return;

    aplicarActualizacion();
}

/** La cola offline vive en IndexedDB: recargar no la pierde. */
export function aplicarActualizacion(): void {
    location.reload();
}
