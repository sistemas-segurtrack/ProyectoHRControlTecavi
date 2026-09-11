import { reactive, readonly } from 'vue';

/**
 * Cola de envíos pendientes (crear ruta / registrar avance) hechos sin
 * conexión. Va en IndexedDB, no en localStorage, porque necesita guardar la
 * foto adjunta (un `File`) — localStorage solo admite strings y tiene poco
 * espacio.
 */

const DB_NOMBRE = 'pwa-outbox';
const DB_VERSION = 1;
const TIENDA = 'envios';

export type TipoEnvio = 'crear-ruta' | 'continuar';

export type EnvioPendiente = {
    /** Id propio de este envío en la cola (no confundir con `idruta`). */
    id: string;
    tipo: TipoEnvio;
    /**
     * Identificador local (no existe en el servidor) para una hoja de ruta
     * armada sin señal. Un `continuar` que dependa de esa ruta todavía sin
     * sincronizar referencia este mismo valor en vez de `idruta` — hasta que
     * el `crear-ruta` correspondiente se sincroniza y se le puede poner la
     * `idruta` real.
     */
    tempId?: string;
    idruta?: string;
    idempotencyKey: string;
    /** Los campos del formulario (y la foto, si hay) como pares clave/valor,
     *  igual que se leerían de un FormData — se reconstruye uno igual al
     *  reintentar. */
    entradas: Array<[string, string | File]>;
    creadoEn: number;
    /**
     * Si el servidor lo rechazó al intentar sincronizarlo (validación u otro
     * error que no sea de red) queda marcado acá en vez de descartarse en
     * silencio — necesita que alguien lo vea y decida, no tiene sentido
     * reintentarlo solo porque casi siempre vuelve a fallar por lo mismo.
     */
    ultimoError?: string;
};

function abrirDB(): Promise<IDBDatabase> {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(DB_NOMBRE, DB_VERSION);
        req.onupgradeneeded = () => {
            req.result.createObjectStore(TIENDA, { keyPath: 'id' });
        };
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error as Error);
    });
}

const resumen = reactive<{ pendientes: number; errores: EnvioPendiente[] }>({
    pendientes: 0,
    errores: [],
});

async function actualizarResumen(): Promise<void> {
    try {
        const todos = await listar();
        resumen.errores = todos.filter((e) => e.ultimoError !== undefined);
        resumen.pendientes = todos.length - resumen.errores.length;
    } catch {
        /* IndexedDB no disponible (privado/incógnito estricto): sin cola */
    }
}
void actualizarResumen();

export async function agregar(
    envio: Omit<EnvioPendiente, 'creadoEn'>,
): Promise<void> {
    const db = await abrirDB();
    await new Promise<void>((resolve, reject) => {
        const tx = db.transaction(TIENDA, 'readwrite');
        tx.objectStore(TIENDA).add({ ...envio, creadoEn: Date.now() });
        tx.oncomplete = () => resolve();
        tx.onerror = () => reject(tx.error as Error);
    });
    await actualizarResumen();
}

export async function listar(): Promise<EnvioPendiente[]> {
    const db = await abrirDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(TIENDA, 'readonly');
        const req = tx.objectStore(TIENDA).getAll();
        req.onsuccess = () =>
            resolve(
                (req.result as EnvioPendiente[]).sort(
                    (a, b) => a.creadoEn - b.creadoEn,
                ),
            );
        req.onerror = () => reject(req.error as Error);
    });
}

export async function eliminar(id: string): Promise<void> {
    const db = await abrirDB();
    await new Promise<void>((resolve, reject) => {
        const tx = db.transaction(TIENDA, 'readwrite');
        tx.objectStore(TIENDA).delete(id);
        tx.oncomplete = () => resolve();
        tx.onerror = () => reject(tx.error as Error);
    });
    await actualizarResumen();
}

export async function actualizar(
    id: string,
    cambios: Partial<EnvioPendiente>,
): Promise<void> {
    const db = await abrirDB();
    await new Promise<void>((resolve, reject) => {
        const tx = db.transaction(TIENDA, 'readwrite');
        const tienda = tx.objectStore(TIENDA);
        const getReq = tienda.get(id);
        getReq.onsuccess = () => {
            const actual = getReq.result as EnvioPendiente | undefined;
            if (actual) tienda.put({ ...actual, ...cambios });
        };
        tx.oncomplete = () => resolve();
        tx.onerror = () => reject(tx.error as Error);
    });
    await actualizarResumen();
}

export function usePendientes() {
    return { resumen: readonly(resumen) };
}
