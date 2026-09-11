<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { RouterView } from 'vue-router';
import { iniciarUbicacion, precargarCamara } from './lib/dispositivo';
import { usePendientes } from './lib/outbox';
import { descartar, procesarCola, sincronizando } from './lib/sincronizar';

const offline = ref(!navigator.onLine);
const { resumen: cola } = usePendientes();
const verErrores = ref(false);

onMounted(() => {
    window.addEventListener('online', () => {
        offline.value = false;
        void procesarCola();
    });
    window.addEventListener('offline', () => (offline.value = true));

    // Permisos que la app necesita para registrar avances: ubicación (se usa
    // automáticamente) y cámara (para la foto del documento).
    iniciarUbicacion();
    void precargarCamara();

    // Por si quedó algo sin enviar de una sesión anterior.
    void procesarCola();
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
        <div
            v-else-if="cola.pendientes > 0"
            class="flex items-center justify-center gap-2 bg-blue-500 px-4 py-1.5 text-center text-xs font-semibold text-white"
        >
            <span>
                {{
                    sincronizando
                        ? 'Enviando cambios pendientes…'
                        : `${cola.pendientes} cambio(s) sin enviar`
                }}
            </span>
            <button
                v-if="!sincronizando"
                type="button"
                class="underline underline-offset-2"
                @click="procesarCola"
            >
                Reintentar ahora
            </button>
        </div>

        <!-- Envíos que el servidor rechazó al sincronizar (no se descartan
             solos: se perdería el avance sin que el conductor se entere). -->
        <div v-if="cola.errores.length > 0" class="bg-rose-600 text-white">
            <button
                type="button"
                class="flex w-full items-center justify-center gap-2 px-4 py-1.5 text-center text-xs font-semibold"
                @click="verErrores = !verErrores"
            >
                {{ cola.errores.length }} cambio(s) no se pudieron enviar —
                {{ verErrores ? 'ocultar' : 'ver' }}
            </button>
            <ul v-if="verErrores" class="flex flex-col gap-2 px-4 pb-3">
                <li
                    v-for="envio in cola.errores"
                    :key="envio.id"
                    class="flex items-start justify-between gap-3 rounded-lg bg-rose-700/60 px-3 py-2 text-xs"
                >
                    <span>{{ envio.ultimoError }}</span>
                    <button
                        type="button"
                        class="shrink-0 font-bold underline underline-offset-2"
                        @click="descartar(envio.id)"
                    >
                        Descartar
                    </button>
                </li>
            </ul>
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
