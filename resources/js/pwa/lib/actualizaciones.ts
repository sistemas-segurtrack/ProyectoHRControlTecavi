import { ref } from 'vue';

/**
 * Aviso de que hay una versión nueva de la app lista para usarse (ver
 * `App.vue`, banner "Actualizar ahora"). El conductor decide cuándo
 * recargar -- forzarlo solo sería seguro para la cola offline (persiste en
 * IndexedDB), pero perdería lo que tuviera a medio llenar en un formulario.
 */
export const hayActualizacion = ref(false);

/**
 * Engancha el ciclo de vida del Service Worker para detectar una versión
 * nueva ya activa y revisar `sw.js` seguido (el navegador por su cuenta solo
 * lo hace de vez en cuando, hasta cada 24h).
 *
 * El propio `sw.js` ya hace `skipWaiting()` + `clients.claim()` sin esperar
 * a que se cierren las pestañas viejas -- no hay una fase "instalado,
 * esperando" que enganchar (el patrón típico de "haz clic para actualizar"
 * no aplica acá). El aviso real y correcto es `controllerchange`: se dispara
 * justo cuando `clients.claim()` hace efecto y una versión nueva pasa a
 * controlar la página.
 */
export function vigilarActualizaciones(
    registro: ServiceWorkerRegistration,
): void {
    // `controllerchange` también se dispara la PRIMERA vez que un SW toma
    // control de una página que todavía no tenía ninguno -- eso es una
    // instalación nueva, no una actualización, así que no debe avisar nada.
    // Se guarda si YA había un controlador antes de que pase cualquier cambio.
    const teniaControladorAlCargar =
        navigator.serviceWorker.controller !== null;

    let yaAvisado = false;
    navigator.serviceWorker.addEventListener('controllerchange', () => {
        if (yaAvisado || !teniaControladorAlCargar) return;
        yaAvisado = true;
        hayActualizacion.value = true;
    });

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') void registro.update();
    });
    // Un conductor que recupera señal en movimiento no debería esperar hasta
    // 5 minutos (o a volver a la app) para enterarse de una versión nueva.
    window.addEventListener('online', () => void registro.update());
    setInterval(() => void registro.update(), 5 * 60 * 1000);
}

export function aplicarActualizacion(): void {
    location.reload();
}
