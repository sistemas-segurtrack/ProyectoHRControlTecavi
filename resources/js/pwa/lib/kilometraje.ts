import type { Orden } from '../stores/auth';

/**
 * Espejo de `ValidaKilometraje::KILOMETRAJE_MAXIMO` (backend): por debajo del
 * máximo del contador de Wialon (4294967) para que una parada en el tope no
 * bloquee la siguiente, que siempre debe ser mayor.
 */
const KILOMETRAJE_MAXIMO = 4294800;

const ENTERO = /^\d+$/;

/**
 * Espejo de `RegistrarOrdenRequest::kilometrajeAnterior()` (backend): km de la
 * última parada de la hoja, sin importar el tramo (el odómetro es uno solo).
 * `null` si no hay paradas o la última no tiene kilometraje.
 */
export function kilometrajeAnterior(ordenes: Orden[]): number | null {
    const km = ordenes.at(-1)?.kilometraje?.trim() ?? '';

    return ENTERO.test(km) ? Number(km) : null;
}

/**
 * Mismo criterio que `ValidaKilometraje` (backend), para avisar al instante
 * — con o sin señal — mientras se escribe. `null` si está vacío (lo
 * obligatorio se revisa al enviar) o es válido; si no, el mensaje a mostrar.
 */
export function errorKilometraje(
    valor: string,
    anterior: number | null,
): string | null {
    const texto = valor.trim();
    if (texto === '') return null;

    if (!ENTERO.test(texto)) {
        return 'El kilometraje debe ser un número entero, sin puntos ni comas.';
    }

    const km = Number(texto);
    if (km > KILOMETRAJE_MAXIMO) {
        return 'Kilometraje No Permitido';
    }
    if (anterior !== null && km <= anterior) {
        return `El kilometraje debe ser mayor al de la parada anterior (${anterior} km).`;
    }

    return null;
}
