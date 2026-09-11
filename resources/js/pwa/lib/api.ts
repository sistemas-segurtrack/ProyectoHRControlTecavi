import { useAuth } from '../stores/auth';

// Vacío salvo que la app vaya detrás de un proxy en subpath (ver config/pwa.php).
const BASE = `${import.meta.env.VITE_PWA_BASE_PATH ?? ''}/api/pwa`;

export class ApiError extends Error {
    constructor(
        message: string,
        public readonly status: number,
        public readonly data: unknown = null,
    ) {
        super(message);
        this.name = 'ApiError';
    }
}

type Opciones = {
    method?: 'GET' | 'POST' | 'PUT' | 'DELETE';
    body?: unknown;
    idempotencyKey?: string;
};

export async function api<T = unknown>(
    path: string,
    opts: Opciones = {},
): Promise<T> {
    const { state, cerrarSesion } = useAuth();

    const esForm = opts.body instanceof FormData;

    const headers: Record<string, string> = { Accept: 'application/json' };
    // El navegador pone el Content-Type (con boundary) para FormData.
    if (opts.body !== undefined && !esForm)
        headers['Content-Type'] = 'application/json';
    if (state.token) headers.Authorization = `Bearer ${state.token}`;
    if (opts.idempotencyKey) headers['Idempotency-Key'] = opts.idempotencyKey;

    let res: Response;
    try {
        res = await fetch(BASE + path, {
            method: opts.method ?? 'GET',
            headers,
            body:
                opts.body === undefined
                    ? undefined
                    : esForm
                      ? (opts.body as FormData)
                      : JSON.stringify(opts.body),
        });
    } catch {
        throw new ApiError('Sin conexión.', 0);
    }

    if (res.status === 401) {
        cerrarSesion();
        throw new ApiError('Tu sesión expiró, vuelve a entrar.', 401);
    }

    let data: unknown = null;
    try {
        data = await res.json();
    } catch {
        /* respuesta sin cuerpo */
    }

    if (!res.ok) {
        const msg =
            (data as { message?: string } | null)?.message ??
            `Error ${res.status}`;
        throw new ApiError(msg, res.status, data);
    }

    return data as T;
}

export function uuid(): string {
    if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
        return crypto.randomUUID();
    }
    return `${Date.now()}-${Math.random().toString(16).slice(2)}-${Math.random().toString(16).slice(2)}`;
}

/**
 * Fecha y hora actual en el formato que espera un `<input type="datetime-local">`
 * (`YYYY-MM-DDTHH:mm`), en la hora LOCAL del dispositivo — no UTC, que es lo
 * que da `toISOString()` y correría la hora mostrada.
 */
export function fechaHoraLocal(): string {
    const d = new Date();
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}
