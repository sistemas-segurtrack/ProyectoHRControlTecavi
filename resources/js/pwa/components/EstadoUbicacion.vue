<script setup lang="ts">
import { Loader2, MapPin, MapPinOff } from '@lucide/vue';
import { computed } from 'vue';
import { useUbicacion } from '../lib/dispositivo';

const { ubicacion } = useUbicacion();

const texto = computed(() => {
    if (ubicacion.coordenada) return `Ubicación: ${ubicacion.coordenada}`;
    if (ubicacion.permiso === 'denegado') {
        return 'Ubicación desactivada — actívala para registrar la coordenada.';
    }
    if (ubicacion.permiso === 'gps-apagado') {
        return 'GPS apagado — actívalo para registrar la coordenada.';
    }
    return 'Obteniendo ubicación…';
});
</script>

<template>
    <p class="flex items-center gap-1.5 text-xs text-gray-400">
        <MapPin v-if="ubicacion.coordenada" class="h-3.5 w-3.5" />
        <MapPinOff
            v-else-if="
                ubicacion.permiso === 'denegado' ||
                ubicacion.permiso === 'gps-apagado'
            "
            class="h-3.5 w-3.5"
        />
        <Loader2 v-else class="h-3.5 w-3.5 animate-spin" />
        {{ texto }}
    </p>
</template>
