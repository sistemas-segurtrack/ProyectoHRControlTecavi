import type { InertiaLinkProps } from '@inertiajs/vue3';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(href: NonNullable<InertiaLinkProps['href']>) {
    return typeof href === 'string' ? href : href?.url;
}

/**
 * URL de un archivo estático de `public/` (logos, íconos) referenciado
 * directo en una plantilla Vue — no puede pasar por el helper `asset()` de
 * Laravel porque ese corre del lado del servidor. Igual que en la PWA
 * (`resources/js/pwa/lib/api.ts`), hace falta anteponer a mano el prefijo
 * del subpath detrás del proxy (p. ej. `/hrcontrol` en producción) o el link
 * queda apuntando a la raíz del dominio y da 404. Reutiliza la misma
 * variable `VITE_PWA_BASE_PATH` — a pesar del nombre, representa el prefijo
 * de despliegue completo, no solo el de la PWA.
 */
export function recursoUrl(ruta: string): string {
    const base = import.meta.env.VITE_PWA_BASE_PATH ?? '';

    return `${base}/recursos/${ruta.replace(/^\/+/, '')}`;
}
