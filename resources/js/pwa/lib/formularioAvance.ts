/**
 * Lo común a los formularios "Nueva Ruta" y "Continuar": las validaciones
 * antes de enviar y el armado del cuerpo (JSON, o FormData si hay adjunto).
 */

export type CuerpoEnvio = FormData | Record<string, string | null>;

/** Lo que expone `AdjuntarDocumento.vue` (`defineExpose`) y se usa acá. */
type Adjunto = {
    activo: boolean;
    listo: boolean;
    anexar: (fd: FormData) => void;
};

/** Primer dato obligatorio que falta (mensaje a mostrar), o `null`. */
export function faltanteAntesDeEnviar(datos: {
    adjunto: Adjunto | null;
    kilometraje: string;
    geocerca: string;
}): string | null {
    if (datos.adjunto && !datos.adjunto.listo) {
        return 'Completa el tipo, el código y la foto del documento adjunto.';
    }
    if (datos.kilometraje.trim() === '') {
        return 'El kilometraje es obligatorio.';
    }
    if (datos.geocerca.trim() === '') {
        return 'El lugar es obligatorio.';
    }

    return null;
}

/** JSON si no hay adjunto; FormData (campos + documento y foto) si lo hay. */
export function armarCuerpo(
    campos: Record<string, string | null>,
    adjunto: Adjunto | null,
): CuerpoEnvio {
    if (!adjunto?.activo) return campos;

    const fd = new FormData();
    for (const [clave, valor] of Object.entries(campos)) {
        if (valor !== null) fd.append(clave, valor);
    }
    adjunto.anexar(fd);

    return fd;
}

/** Texto recortado, o `null` si queda vacío. */
export function textoONulo(valor: string): string | null {
    return valor.trim() || null;
}
