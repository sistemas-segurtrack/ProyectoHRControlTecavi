<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { api } from '../lib/api';
import { useAuth, type Ruta } from '../stores/auth';

const router = useRouter();
const { state, setRutaActiva, cerrarSesion } = useAuth();
const sincronizando = ref(false);

onMounted(async () => {
    sincronizando.value = true;
    try {
        const res = await api<{ data: Ruta | null }>('/rutas/activa');
        setRutaActiva(res.data);
    } catch {
        /* offline: se usa lo que haya en memoria */
    } finally {
        sincronizando.value = false;
    }
});

function salir(): void {
    api('/logout', { method: 'POST' }).catch(() => {
        /* da igual si falla */
    });
    cerrarSesion();
    router.replace({ name: 'login' });
}
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-6">
        <header class="flex items-start justify-between">
            <div>
                <p class="text-sm text-gray-400">Conductor</p>
                <h1 class="text-lg font-bold">
                    {{ state.conductor?.nombre ?? '—' }}
                </h1>
                <p class="text-xs text-gray-400">
                    DNI {{ state.conductor?.dni }}
                    <span v-if="state.conductor?.licencia">
                        · Lic. {{ state.conductor.licencia }}
                    </span>
                </p>
            </div>
            <button
                class="rounded-lg px-3 py-1.5 text-xs font-semibold text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"
                @click="salir"
            >
                Salir
            </button>
        </header>

        <div class="flex flex-1 flex-col justify-center gap-4">
            <!-- Con ruta en curso -->
            <template v-if="state.rutaActiva">
                <div
                    class="rounded-2xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-950/40"
                >
                    <p
                        class="text-xs font-bold tracking-wide text-blue-500 uppercase"
                    >
                        Hoja de ruta en curso
                    </p>
                    <p
                        class="mt-1 font-mono text-lg font-bold text-blue-700 dark:text-blue-300"
                    >
                        {{ state.rutaActiva.idruta }}
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        {{ state.rutaActiva.placa }}
                        <span v-if="state.rutaActiva.carreta">
                            / {{ state.rutaActiva.carreta }}
                        </span>
                        · {{ state.rutaActiva.ordenes.length }} orden(es)
                    </p>
                </div>

                <button
                    class="h-16 w-full rounded-2xl bg-[#b51927] text-xl font-bold text-white shadow-lg shadow-[#b51927]/25 transition active:scale-[.98]"
                    @click="router.push({ name: 'continuar' })"
                >
                    Continuar
                </button>
            </template>

            <!-- Sin ruta -->
            <template v-else>
                <p class="text-center text-sm text-gray-400">
                    No tienes ninguna hoja de ruta abierta.
                </p>
                <button
                    class="h-16 w-full rounded-2xl bg-[#b51927] text-xl font-bold text-white shadow-lg shadow-[#b51927]/25 transition active:scale-[.98]"
                    @click="router.push({ name: 'nueva-ruta' })"
                >
                    Nueva Ruta
                </button>
            </template>

            <p v-if="sincronizando" class="text-center text-xs text-gray-400">
                Sincronizando…
            </p>
        </div>
    </div>
</template>
