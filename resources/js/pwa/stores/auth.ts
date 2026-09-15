import { computed, reactive } from 'vue';

export type Conductor = {
    id: number;
    wialon_conductor_id: number;
    codigo: string | null;
    dni: string | null;
    nombre: string;
    licencia: string | null;
    telefono: string | null;
    descripcion: string | null;
};

export type TipoDocumento = {
    id: number;
    nombre: string | null;
    condiciona_fin: boolean;
};

export type Catalogos = {
    placas: string[];
    // Placa -> idruta de la hoja EN RUTA de verdad ahora mismo (tramo
    // abierto, sin cerrar), sin importar el conductor que la inició. "Nueva
    // Ruta" la usa para avisar al elegir la unidad que ya está en ruta.
    unidades_en_ruta: Record<string, string>;
    carretas: string[];
    geocercas: string[];
    copilotos: string[];
    tipos_documento: TipoDocumento[];
};

export type Documento = {
    id: number;
    tipo_documento: string | null;
    documento: string | null;
    cantidad: string | null;
    producto: string | null;
    envase: string | null;
    peso_neto: string | null;
    peso_bruto: string | null;
    imagen: string | null;
};

export type Orden = {
    id: number;
    orden: number | null;
    geocerca: string | null;
    coordenada: string | null;
    kilometraje: string | null;
    observacion: string | null;
    estado: string | null;
    estado_label: string;
    fh_registro: string | null;
    documentos: Documento[];
};

export type Ruta = {
    idruta: string;
    placa: string | null;
    piloto: string | null;
    copiloto: string | null;
    precintos: string | null;
    carreta: string | null;
    estado: string | null;
    ordenes: Orden[];
};

/** Espejo de `Ruta::FINALIZADA` (backend). */
const ESTADO_FINALIZADA = 'F';

/**
 * Una ruta finalizada (p. ej. por un documento adjunto que la finaliza,
 * como un recibo de combustible) ya no tiene nada pendiente — no debe
 * quedar como "en curso" en `state.rutaActiva`, o Home seguiría ofreciendo
 * "Continuar" sobre algo que ya terminó.
 */
export function rutaFinalizada(ruta: Ruta): boolean {
    return ruta.estado === ESTADO_FINALIZADA;
}

export type Sesion = {
    token: string;
    conductor: Conductor;
    catalogos: Catalogos;
    ruta_activa: Ruta | null;
};

const LS = 'pwa_conductor';

const CATALOGOS_VACIOS: Catalogos = {
    placas: [],
    unidades_en_ruta: {},
    carretas: [],
    geocercas: [],
    copilotos: [],
    tipos_documento: [],
};

const state = reactive({
    token: null as string | null,
    conductor: null as Conductor | null,
    catalogos: { ...CATALOGOS_VACIOS } as Catalogos,
    rutaActiva: null as Ruta | null,
});

try {
    const raw = localStorage.getItem(LS);
    if (raw) {
        const guardado = JSON.parse(raw) as Partial<typeof state>;
        state.token = guardado.token ?? null;
        state.conductor = guardado.conductor ?? null;
        state.catalogos = {
            ...CATALOGOS_VACIOS,
            ...guardado.catalogos,
        };
        // La ruta activa también se persiste: si la app se cierra sin señal
        // (por ejemplo con una hoja armada sin conexión, todavía en la cola
        // de envío), al reabrirla debe seguir viéndose hasta que se logre
        // sincronizar — no solo mientras el proceso de JS siga vivo.
        state.rutaActiva = guardado.rutaActiva ?? null;
    }
} catch {
    /* almacenamiento no disponible */
}

function persistir(): void {
    try {
        localStorage.setItem(
            LS,
            JSON.stringify({
                token: state.token,
                conductor: state.conductor,
                catalogos: state.catalogos,
                rutaActiva: state.rutaActiva,
            }),
        );
    } catch {
        /* ignore */
    }
}

const iniciarSesion = (data: Sesion): void => {
    state.token = data.token;
    state.conductor = data.conductor;
    state.catalogos = { ...CATALOGOS_VACIOS, ...data.catalogos };
    state.rutaActiva = data.ruta_activa;
    persistir();
};

const setCatalogos = (c: Catalogos): void => {
    state.catalogos = { ...CATALOGOS_VACIOS, ...c };
    persistir();
};

const setRutaActiva = (r: Ruta | null): void => {
    state.rutaActiva = r;
    persistir();
};

const cerrarSesion = (): void => {
    state.token = null;
    state.conductor = null;
    state.rutaActiva = null;
    state.catalogos = { ...CATALOGOS_VACIOS };
    try {
        localStorage.removeItem(LS);
    } catch {
        /* ignore */
    }
};

const autenticado = computed(() => state.token !== null);

export function useAuth() {
    return {
        state,
        autenticado,
        iniciarSesion,
        setCatalogos,
        setRutaActiva,
        cerrarSesion,
    };
}
