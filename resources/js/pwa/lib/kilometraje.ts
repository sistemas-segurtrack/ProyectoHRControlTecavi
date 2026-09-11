import type { Catalogos, Orden } from '../stores/auth';

/**
 * Espejo, en el cliente, de `ValidaKilometraje::validarKilometrajeNoRetrocede`
 * (backend): mismo cálculo de la referencia (el mayor entre el contador de
 * Wialon cacheado y lo que corresponda de esta misma hoja de ruta), para
 * poder avisar al instante — con o sin señal — en vez de esperar la
 * respuesta del servidor. La regla del servidor sigue siendo la que manda:
 * esto es solo para que el conductor no llene el formulario a ciegas.
 *
 * Cada tramo (parada impar = inicio, parada par = fin) es independiente: el
 * kilometraje de un tramo ya cerrado no condiciona el del tramo siguiente.
 * Si el próximo orden a registrar CIERRA el tramo abierto (par), la
 * referencia de la ruta es el kilometraje de esa misma parada de inicio; si
 * en cambio ABRE un tramo nuevo (impar), no hay referencia de la ruta — solo
 * sigue aplicando el contador de Wialon.
 */
export function referenciaKilometraje(
    catalogos: Catalogos,
    placa: string | null,
    ordenesPrevias: Orden[] = [],
): number {
    const deWialon = placa ? (catalogos.kilometrajes[placa] ?? 0) : 0;

    const ultima = ordenesPrevias.at(-1);
    const siguienteOrden = (ultima?.orden ?? 0) + 1;
    const cierraTramo = siguienteOrden % 2 === 0;

    const deLaRuta = cierraTramo ? kilometrajeDe(ultima) : 0;

    return Math.max(deWialon, deLaRuta);
}

function kilometrajeDe(orden: Orden | undefined): number {
    if (!orden || orden.kilometraje === null) return 0;
    const km = Number(orden.kilometraje);
    return Number.isFinite(km) ? km : 0;
}

/** `null` si el valor está vacío o es válido; el mensaje a mostrar si no. */
export function errorKilometraje(
    valor: string,
    referencia: number,
): string | null {
    const texto = valor.trim();
    if (texto === '') return null;

    const km = Number(texto);
    if (!Number.isFinite(km)) return null;

    return km < referencia
        ? `El kilometraje no puede ser menor al último registrado (${referencia} km).`
        : null;
}
