<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { RouterView } from 'vue-router';
import { iniciarUbicacion, precargarCamara } from './lib/dispositivo';

const offline = ref(!navigator.onLine);

onMounted(() => {
    window.addEventListener('online', () => (offline.value = false));
    window.addEventListener('offline', () => (offline.value = true));

    // Permisos que la app necesita para registrar avances: ubicación (se usa
    // automáticamente) y cámara (para la foto del documento).
    iniciarUbicacion();
    void precargarCamara();
});
</script>

<template>
    <div
        class="mx-auto flex min-h-dvh w-full max-w-md flex-col bg-gray-50 text-gray-900 dark:bg-gray-950 dark:text-gray-100"
    >
        <div
            v-if="offline"
            class="bg-amber-500 px-4 py-1.5 text-center text-xs font-semibold text-white"
        >
            Sin conexión — los cambios se guardarán y enviarán al reconectar
        </div>

        <RouterView v-slot="{ Component }">
            <Transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="translate-x-4 opacity-0"
                leave-active-class="transition duration-100 ease-in"
                leave-to-class="-translate-x-4 opacity-0"
                mode="out-in"
            >
                <component :is="Component" />
            </Transition>
        </RouterView>
    </div>
</template>
