import { reactive, readonly } from 'vue';

/**
 * Ubicación del dispositivo. Se pide permiso una sola vez al arrancar la app y
 * a partir de ahí la coordenada se actualiza sola (watchPosition); los
 * formularios la leen sin pedir nada al conductor.
 */
type EstadoUbicacion = {
    coordenada: string | null;
    permiso: 'desconocido' | 'concedido' | 'denegado' | 'no-soportado';
    error: string | null;
};

const estado = reactive<EstadoUbicacion>({
    coordenada: null,
    permiso: 'desconocido',
    error: null,
});

let watchId: number | null = null;

function fija(pos: GeolocationPosition): void {
    estado.coordenada = `${pos.coords.latitude.toFixed(6)},${pos.coords.longitude.toFixed(6)}`;
    estado.permiso = 'concedido';
    estado.error = null;
}

function falla(err: GeolocationPositionError): void {
    estado.error = err.message;
    if (err.code === err.PERMISSION_DENIED) estado.permiso = 'denegado';
}

/**
 * Arranca el seguimiento de ubicación (idempotente). Llamar al montar la app.
 */
export function iniciarUbicacion(): void {
    if (!('geolocation' in navigator)) {
        estado.permiso = 'no-soportado';
        return;
    }
    if (watchId !== null) return;

    navigator.geolocation.getCurrentPosition(fija, falla, {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 30000,
    });

    watchId = navigator.geolocation.watchPosition(fija, falla, {
        enableHighAccuracy: true,
        timeout: 15000,
        maximumAge: 15000,
    });
}

/**
 * Pre-solicita el permiso de cámara al arrancar (best-effort). En móvil el
 * input `capture` abre la cámara igual; esto solo adelanta el diálogo.
 */
export async function precargarCamara(): Promise<void> {
    if (!navigator.mediaDevices?.getUserMedia) return;
    try {
        const stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment' },
        });
        stream.getTracks().forEach((t) => t.stop());
    } catch {
        /* si el conductor lo niega, seguirá pudiendo elegir archivo */
    }
}

export function useUbicacion() {
    return { ubicacion: readonly(estado) };
}
