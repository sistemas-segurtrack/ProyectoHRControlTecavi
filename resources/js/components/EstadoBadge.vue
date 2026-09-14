<script setup lang="ts">
const props = defineProps<{
    /** Código del estado: por tramo 'ER' (en ruta) | 'FI' (finalizado); de
     *  toda la hoja (`ruta.estado`) 'A' (activa) | 'F' (finalizada). */
    estado: string | null;
    /** Etiqueta legible opcional; si falta se deriva del código. */
    label?: string | null;
}>();

const TEXTO: Record<string, string> = {
    ER: 'EN RUTA',
    FI: 'FINALIZADO',
    A: 'ACTIVA',
    F: 'FINALIZADA',
};

const clase = (): string => {
    switch (props.estado) {
        case 'ER':
            return 'bg-blue-700 text-white';
        case 'FI':
            return 'bg-emerald-700 text-white';
        // ruta.estado: activa = verde, finalizada = rojo (pedido del usuario).
        case 'A':
            return 'bg-emerald-700 text-white';
        case 'F':
            return 'bg-red-700 text-white';
        default:
            return 'bg-gray-700 text-white';
    }
};

const texto = (): string =>
    props.label || TEXTO[props.estado ?? ''] || props.estado || '';
</script>

<template>
    <span
        v-if="estado"
        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold"
        :class="clase()"
    >
        {{ texto() }}
    </span>
    <span v-else class="text-gray-400">-</span>
</template>
