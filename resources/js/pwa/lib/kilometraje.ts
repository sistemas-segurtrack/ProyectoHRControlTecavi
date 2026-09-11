import type { Catalogos, Orden } from '../stores/auth';

/**
 * Espejo, en el cliente, de `ValidaKilometraje::validarKilometrajeNoRetrocede`
 * (backend): mismo cálculo de la referencia (el mayor entre el contador de
 * Wialon cacheado y lo ya registrado en esta misma hoja de ruta), para poder
 * avisar al instante — con o sin señal — en vez de esperar la respuesta del
 * servidor. La regla del servidor sigue siendo la que manda: esto es solo
 * para que el conductor no llene el formulario a ciegas.
 */
export function referenciaKilometraje(
    catalogos: Catalogos,
    placa: string | null,
    ordenesPrevias: Orden[] = [],
): number {
    const deWialon = placa ? (catalogos.kilometrajes[placa] ?? 0) : 0;
    const deLaRuta = ordenesPrevias.reduce((max, o) => {
        const km = o.kilometraje !== null ? Number(o.kilometraje) : NaN;
        return Number.isFinite(km) ? Math.max(max, km) : max;
    }, 0);
    return Math.max(deWialon, deLaRuta);
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
