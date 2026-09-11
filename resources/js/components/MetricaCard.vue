<script setup lang="ts">
import { ref } from 'vue';

type Unidad = { vehiculo: string | null; ruta: string | null };

const props = defineProps<{
    label: string;
    valor: number;
    tono: 'slate' | 'blue' | 'yellow' | 'green' | 'rose' | 'amber' | 'emerald';
    variante?: 'suave' | 'solido';
    unidades?: Unidad[] | null;
    titulo?: string;
    labelPequeno?: boolean;
    // Hacia dónde abre el popover según la columna (responsive móvil/escritorio).
    alinear?:
        | 'izquierda'
        | 'derecha'
        | 'derecha-izquierda'
        | 'izquierda-derecha';
    // Muestra un placeholder pulsante en vez de `valor` (primera carga sin datos aún).
    cargando?: boolean;
    // Resalta la tarjeta con un anillo (p. ej. cuando su filtro asociado está activo).
    activo?: boolean;
}>();

const abierto = ref(false);

// Alineación responsive: abre hacia el interior de la pantalla según la columna en cada breakpoint.
const ALINEACION: Record<string, string> = {
    izquierda: 'left-0',
    derecha: 'right-0',
    'derecha-izquierda': 'right-0 md:left-0 md:right-auto',
    'izquierda-derecha': 'left-0 md:right-0 md:left-auto',
};

// Clases completas por tono (literales para que Tailwind las detecte).
const TONOS: Record<
    string,
    { card: string; label: string; valor: string; header: string; chip: string }
> = {
    slate: {
        card: 'border-slate-200 bg-slate-50 dark:border-slate-800/40 dark:bg-slate-950/40',
        label: 'text-slate-500 dark:text-slate-500',
        valor: 'text-slate-700 dark:text-slate-300',
        header: 'bg-slate-600 text-white',
        chip: 'bg-slate-100 text-slate-700 ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-700',
    },
    blue: {
        card: 'border-blue-200 bg-blue-50 dark:border-blue-800/40 dark:bg-blue-950/40',
        label: 'text-blue-500 dark:text-blue-500',
        valor: 'text-blue-700 dark:text-blue-400',
        header: 'bg-blue-600 text-white',
        chip: 'bg-blue-100 text-blue-700 ring-1 ring-blue-200 dark:bg-blue-900/50 dark:text-blue-200 dark:ring-blue-800',
    },
    yellow: {
        card: 'border-yellow-200 bg-yellow-50 dark:border-yellow-800/40 dark:bg-yellow-950/40',
        label: 'text-yellow-500 dark:text-yellow-500',
        valor: 'text-yellow-700 dark:text-yellow-400',
        header: 'bg-yellow-500 text-white',
        chip: 'bg-yellow-100 text-yellow-800 ring-1 ring-yellow-200 dark:bg-yellow-900/50 dark:text-yellow-200 dark:ring-yellow-800',
    },
    green: {
        card: 'border-green-200 bg-green-50 dark:border-green-800/40 dark:bg-green-950/40',
        label: 'text-green-500 dark:text-green-500',
        valor: 'text-green-700 dark:text-green-400',
        header: 'bg-green-600 text-white',
        chip: 'bg-green-100 text-green-700 ring-1 ring-green-200 dark:bg-green-900/50 dark:text-green-200 dark:ring-green-800',
    },
    rose: {
        card: 'border-rose-200 bg-rose-50 dark:border-rose-800/40 dark:bg-rose-950/40',
        label: 'text-rose-500 dark:text-rose-500',
        valor: 'text-rose-700 dark:text-rose-400',
        header: 'bg-rose-600 text-white',
        chip: 'bg-rose-100 text-rose-700 ring-1 ring-rose-200 dark:bg-rose-900/50 dark:text-rose-200 dark:ring-rose-800',
    },
    amber: {
        card: 'border-amber-200 bg-amber-50 dark:border-amber-800/40 dark:bg-amber-950/40',
        label: 'text-amber-500 dark:text-amber-500',
        valor: 'text-amber-700 dark:text-amber-400',
        header: 'bg-amber-600 text-white',
        chip: 'bg-amber-100 text-amber-700 ring-1 ring-amber-200 dark:bg-amber-900/50 dark:text-amber-200 dark:ring-amber-800',
    },
    emerald: {
        card: 'border-emerald-200 bg-emerald-50 dark:border-emerald-800/40 dark:bg-emerald-950/40',
        label: 'text-emerald-500 dark:text-emerald-500',
        valor: 'text-emerald-700 dark:text-emerald-400',
        header: 'bg-emerald-600 text-white',
        chip: 'bg-emerald-100 text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-900/50 dark:text-emerald-200 dark:ring-emerald-800',
    },
};

const SOLIDOS: Record<string, string> = {
    slate: 'border-slate-600 bg-slate-600 text-white shadow-sm',
    blue: 'border-blue-600 bg-blue-600 text-white shadow-sm',
    yellow: 'border-yellow-500 bg-yellow-500 text-white shadow-sm',
    green: 'border-green-600 bg-green-600 text-white shadow-sm',
    rose: 'border-rose-600 bg-rose-600 text-white shadow-sm',
    amber: 'border-amber-500 bg-amber-500 text-white shadow-sm',
    emerald: 'border-emerald-600 bg-emerald-600 text-white shadow-sm',
};

function tieneDetalle(): boolean {
    return Array.isArray(props.unidades);
}
</script>

<template>
    <div
        class="relative rounded-xl border px-5 py-4"
        :class="[
            variante === 'solido' ? SOLIDOS[tono] : TONOS[tono].card,
            tieneDetalle() ? 'cursor-help' : '',
            activo
                ? 'ring-2 ring-white/80 ring-offset-2 ring-offset-white dark:ring-offset-gray-950'
                : '',
        ]"
        :title="titulo"
        @mouseenter="abierto = tieneDetalle()"
        @mouseleave="abierto = false"
    >
        <p
            class="font-semibold uppercase"
            :class="[
                labelPequeno ? 'text-xs' : 'text-sm',
                variante === 'solido' ? 'text-white/90' : TONOS[tono].label,
            ]"
        >
            {{ label }}
        </p>
        <p
            class="mt-1.5 text-3xl font-bold"
            :class="variante === 'solido' ? 'text-white' : TONOS[tono].valor"
        >
            <span
                v-if="cargando"
                class="inline-block h-8 w-16 animate-pulse rounded-lg"
                :class="
                    variante === 'solido' ? 'bg-white/30' : 'bg-gray-400/25'
                "
            />
            <template v-else>{{ valor }}</template>
        </p>

        <!-- Popover con las unidades de la métrica -->
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="-translate-y-1 opacity-0"
            leave-active-class="transition duration-100 ease-in"
            leave-to-class="-translate-y-1 opacity-0"
        >
            <div
                v-if="abierto && unidades && unidades.length"
                class="absolute top-full z-30 w-[min(20rem,calc(100vw-1.5rem))] overflow-hidden rounded-xl border border-gray-200 bg-white shadow-2xl ring-1 ring-black/5 dark:border-gray-700 dark:bg-gray-800"
                :class="ALINEACION[alinear ?? 'izquierda']"
            >
                <div
                    class="flex items-center justify-between gap-2 px-3 py-2 text-xs font-bold tracking-wide uppercase"
                    :class="TONOS[tono].header"
                >
                    <span>{{ label }}</span>
                    <span
                        class="rounded-full bg-white/25 px-2 py-0.5 text-[11px] font-extrabold tabular-nums"
                    >
                        {{ unidades.length }}
                    </span>
                </div>
                <div
                    class="flex max-h-64 flex-wrap content-start gap-1.5 overflow-y-auto p-3"
                >
                    <span
                        v-for="u in unidades"
                        :key="u.vehiculo ?? ''"
                        class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold tracking-wide"
                        :class="TONOS[tono].chip"
                    >
                        {{ u.vehiculo ?? '—' }}
                    </span>
                </div>
            </div>
        </Transition>

        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="-translate-y-1 opacity-0"
            leave-active-class="transition duration-100 ease-in"
            leave-to-class="-translate-y-1 opacity-0"
        >
            <div
                v-if="abierto && unidades && !unidades.length"
                class="absolute top-full z-30 w-[min(14rem,calc(100vw-1.5rem))] rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-400 shadow-xl dark:border-gray-700 dark:bg-gray-800"
                :class="ALINEACION[alinear ?? 'izquierda']"
            >
                Sin unidades
            </div>
        </Transition>
    </div>
</template>
