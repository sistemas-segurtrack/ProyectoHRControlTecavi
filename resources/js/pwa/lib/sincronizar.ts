import { ref } from 'vue';
import { rutaFinalizada, useAuth, type Orden, type Ruta } from '../stores/auth';
import { api, ApiError, uuid } from './api';
import {
    actualizar,
    agregar,
    eliminar,
    listar,
    type EnvioPendiente,
} from './outbox';

/**
 * "Nueva Ruta" y "Continuar" sin señal: en vez de fallar, se arma un estado
 * local optimista (para que la app se sienta igual de responsiva) y el envío
 * real se guarda en la cola de `outbox.ts`, a reintentar cuando vuelva la
 * conexión. `procesarCola()` la vacía en orden, reutilizando el mismo
 * `Idempotency-Key` guardado — reenviar dos veces nunca duplica nada porque
 * el servidor ya lo cachea 24h (`pwa.idempotent`).
 */

export const sincronizando = ref(false);

// Prefijo de una `idruta` local: nunca choca con una real ("T000123").
const PREFIJO_LOCAL = 'LOCAL-';

function formDataDeEntradas(entradas: EnvioPendiente['entradas']): FormData {
    const fd = new FormData();
    for (const [k, v] of entradas) fd.append(k, v);
    return fd;
}

function entradasDeCuerpo(
    cuerpo: FormData | Record<string, string | null>,
): EnvioPendiente['entradas'] {
    if (cuerpo instanceof FormData) {
        return Array.from(cuerpo.entries()) as EnvioPendiente['entradas'];
    }
    return Object.entries(cuerpo).filter(
        (e): e is [string, string] => e[1] !== null,
    );
}

type DatosOrdenLocal = {
    geocerca: string | null;
    coordenada: string | null;
    kilometraje: string | null;
    fhRegistro: string;
    finaliza: boolean;
};

function ordenLocal(numero: number, datos: DatosOrdenLocal): Orden {
    return {
        id: 0,
        orden: numero,
        geocerca: datos.geocerca,
        coordenada: datos.coordenada,
        kilometraje: datos.kilometraje,
        observacion: null,
        estado: datos.finaliza ? 'FI' : 'ER',
        estado_label: datos.finaliza ? 'FINALIZADO' : 'EN RUTA',
        fh_registro: datos.fhRegistro,
        documentos: [],
    };
}

/**
 * Arma la hoja de ruta sin señal: queda visible de inmediato en la pantalla
 * de inicio con una `idruta` local (`LOCAL-...`), y el envío real se encola.
 */
export async function encolarCrearRuta(
    cuerpo: FormData | Record<string, string | null>,
    datos: {
        placa: string;
        piloto: string;
        copiloto: string | null;
        precintos: string | null;
        carreta: string | null;
    } & DatosOrdenLocal,
): Promise<Ruta> {
    const tempId = `${PREFIJO_LOCAL}${uuid().slice(0, 8).toUpperCase()}`;

    await agregar({
        id: uuid(),
        tipo: 'crear-ruta',
        tempId,
        idempotencyKey: uuid(),
        entradas: entradasDeCuerpo(cuerpo),
    });

    return {
        idruta: tempId,
        placa: datos.placa,
        piloto: datos.piloto,
        copiloto: datos.copiloto,
        precintos: datos.precintos,
        carreta: datos.carreta,
        estado: datos.finaliza ? 'F' : 'A',
        ordenes: [ordenLocal(1, datos)],
    };
}

/**
 * Registra un avance sin señal sobre la ruta activa (ya sea una real o una
 * todavía sin sincronizar) — igual, queda visible de inmediato.
 */
export async function encolarContinuar(
    cuerpo: FormData | Record<string, string | null>,
    rutaActual: Ruta,
    datos: DatosOrdenLocal,
): Promise<Ruta> {
    const esLocal = rutaActual.idruta.startsWith(PREFIJO_LOCAL);

    await agregar({
        id: uuid(),
        tipo: 'continuar',
        ...(esLocal
            ? { tempId: rutaActual.idruta }
            : { idruta: rutaActual.idruta }),
        idempotencyKey: uuid(),
        entradas: entradasDeCuerpo(cuerpo),
    });

    const numero = (rutaActual.ordenes.at(-1)?.orden ?? 0) + 1;
    const previas = rutaActual.ordenes.map((o) =>
        o.estado === 'ER'
            ? { ...o, estado: 'FI', estado_label: 'FINALIZADO' }
            : o,
    );

    return {
        ...rutaActual,
        estado: datos.finaliza ? 'F' : rutaActual.estado,
        ordenes: [...previas, ordenLocal(numero, datos)],
    };
}

type ResultadoEnvio =
    | 'sincronizado'
    | 'red-caida'
    | 'sin-avance'
    | 'descartado'
    | 'error';

async function procesarUno(envio: EnvioPendiente): Promise<ResultadoEnvio> {
    const { state, setRutaActiva } = useAuth();

    // Un 'continuar' que dependía de un 'crear-ruta' todavía no
    // sincronizado: se deja para la próxima pasada.
    if (envio.tipo === 'continuar' && !envio.idruta) {
        return 'sin-avance';
    }

    const ruta =
        envio.tipo === 'crear-ruta'
            ? '/rutas'
            : `/rutas/${envio.idruta}/ordenes`;

    try {
        const res = await api<{ data: Ruta }>(ruta, {
            method: 'POST',
            idempotencyKey: envio.idempotencyKey,
            body: formDataDeEntradas(envio.entradas),
        });

        if (envio.tipo === 'crear-ruta' && envio.tempId) {
            // Los 'continuar' encolados sobre la ruta local ya pueden
            // apuntar a la idruta real.
            for (const dep of await listar()) {
                if (dep.tipo === 'continuar' && dep.tempId === envio.tempId) {
                    await actualizar(dep.id, {
                        idruta: res.data.idruta,
                        tempId: undefined,
                    });
                }
            }
            // Si ya se sabía finalizada (el aviso optimista ya la había
            // vaciado de `rutaActiva` al registrarla), esto no la aplica; y
            // si el servidor la finalizó por su cuenta, tampoco hay que
            // dejarla "en curso" — de cualquier modo, `rutaFinalizada()`
            // manda.
            if (state.rutaActiva?.idruta === envio.tempId) {
                setRutaActiva(rutaFinalizada(res.data) ? null : res.data);
            }
        } else if (state.rutaActiva?.idruta === envio.idruta) {
            setRutaActiva(rutaFinalizada(res.data) ? null : res.data);
        }

        await eliminar(envio.id);
        return 'sincronizado';
    } catch (e) {
        if (e instanceof ApiError && e.status === 0) return 'red-caida';

        if (
            e instanceof ApiError &&
            e.status === 409 &&
            envio.tipo === 'crear-ruta'
        ) {
            // El servidor ya tenía otra ruta activa: la armada sin señal no
            // vale — se descarta junto con lo que dependiera de ella y se
            // reemplaza por la real.
            for (const dep of await listar()) {
                if (dep.tipo === 'continuar' && dep.tempId === envio.tempId) {
                    await eliminar(dep.id);
                }
            }
            await eliminar(envio.id);
            try {
                const real = await api<{ data: Ruta | null }>('/rutas/activa');
                setRutaActiva(real.data);
            } catch {
                /* se reintenta en la próxima sincronización */
            }
            return 'descartado';
        }

        // Error de validación u otro no recuperable: reintentarlo solo no
        // serviría de nada, pero descartarlo en silencio perdería el avance
        // sin que el conductor se entere — queda marcado y visible en vez de
        // borrarse.
        const mensaje =
            e instanceof ApiError ? e.message : 'No se pudo enviar.';
        await actualizar(envio.id, { ultimoError: mensaje });
        return 'error';
    }
}

/**
 * Vacía la cola en orden. Se llama al volver la señal y al abrir la app. Los
 * envíos ya marcados con `ultimoError` NO se reintentan solos — casi siempre
 * es un error que se va a repetir (p. ej. un kilometraje que ya no es
 * válido), así que insistir a lo tonto no ayuda; quedan visibles para que el
 * conductor decida (ver `descartar()`).
 */
export async function procesarCola(): Promise<void> {
    if (sincronizando.value) return;
    sincronizando.value = true;
    try {
        for (;;) {
            const cola = (await listar()).filter(
                (e) => e.ultimoError === undefined,
            );
            if (cola.length === 0) return;

            let avanzo = false;
            for (const envio of cola) {
                const resultado = await procesarUno(envio);
                if (resultado === 'red-caida') return;
                if (resultado !== 'sin-avance') avanzo = true;
            }
            // Si nadie avanzó, lo que queda son 'continuar' esperando su
            // 'crear-ruta' — no tiene sentido seguir dando vueltas.
            if (!avanzo) return;
        }
    } finally {
        sincronizando.value = false;
    }
}

/** Descarta a mano un envío marcado con error — se pierde ese avance. */
export async function descartar(id: string): Promise<void> {
    const envio = (await listar()).find((e) => e.id === id);
    await eliminar(id);
    if (!envio) return;

    // El estado local optimista (la ruta o el avance que se veían "en
    // curso" mientras esto seguía en la cola) ya no va a llegar a
    // existir de verdad — si sigue mostrándose, el conductor queda
    // atascado creyendo que tiene una hoja de ruta que el servidor nunca
    // tuvo.
    const { state, setRutaActiva } = useAuth();
    const idrutaDelEnvio = envio.idruta ?? envio.tempId;
    const rutaActiva = state.rutaActiva;
    if (rutaActiva === null || rutaActiva.idruta !== idrutaDelEnvio) return;

    if (envio.tipo === 'crear-ruta') {
        setRutaActiva(null);
    } else {
        // Se quita el último orden: es el que este envío había agregado
        // de forma optimista.
        setRutaActiva({
            ...rutaActiva,
            ordenes: rutaActiva.ordenes.slice(0, -1),
        });
    }
}
