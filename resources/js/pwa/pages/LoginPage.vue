<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { api, ApiError } from '../lib/api';
import { procesarCola } from '../lib/sincronizar';
import { useAuth, type Sesion } from '../stores/auth';

const router = useRouter();
const { iniciarSesion } = useAuth();

// `public/recursos/logo-segurtrack.png` no pasa por Vite (no se referencia
// desde ningún import), así que necesita el mismo prefijo de subpath que el
// resto de la PWA (router.ts, api.ts) en vez de una ruta absoluta fija —
// si no, detrás de un proxy en subpath pide la imagen fuera de `/hrcontrol`
// y el navegador la recibe 404.
const logoUrl = `${import.meta.env.VITE_PWA_BASE_PATH ?? ''}/recursos/logo-segurtrack.png`;

const codigo = ref('');
const password = ref('');
const cargando = ref(false);
const error = ref('');

async function entrar(): Promise<void> {
    error.value = '';
    cargando.value = true;
    try {
        const sesion = await api<Sesion>('/login', {
            method: 'POST',
            body: { codigo: codigo.value.trim(), password: password.value },
        });
        iniciarSesion(sesion);
        // Envíos que quedaron pendientes de este conductor (p. ej. si la
        // sesión se cerró con avances sin señal todavía en la cola).
        void procesarCola();
        router.replace({ name: 'home' });
    } catch (e) {
        error.value =
            e instanceof ApiError
                ? e.status === 422
                    ? 'Código o contraseña incorrectos.'
                    : e.message
                : 'No se pudo iniciar sesión.';
    } finally {
        cargando.value = false;
    }
}
</script>

<template>
    <div class="flex flex-1 flex-col items-center justify-center gap-10 p-6">
        <img
            :src="logoUrl"
            alt="Segurtrack"
            class="w-60 max-w-[75%] dark:rounded-xl dark:bg-white dark:p-3"
        />

        <form class="flex w-full flex-col gap-4" @submit.prevent="entrar">
            <label class="flex flex-col gap-1">
                <span
                    class="text-xs font-bold tracking-wide text-gray-500 uppercase"
                    >Código</span
                >
                <input
                    v-model="codigo"
                    type="text"
                    autocapitalize="characters"
                    autocomplete="username"
                    required
                    class="h-13 w-full rounded-xl border border-gray-300 bg-white px-4 text-lg tracking-wide text-gray-900 focus:border-[#b51927] focus:ring-2 focus:ring-[#b51927]/25 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                />
            </label>

            <label class="flex flex-col gap-1">
                <span
                    class="text-xs font-bold tracking-wide text-gray-500 uppercase"
                    >Contraseña</span
                >
                <input
                    v-model="password"
                    type="password"
                    autocomplete="current-password"
                    required
                    class="h-13 w-full rounded-xl border border-gray-300 bg-white px-4 text-lg text-gray-900 focus:border-[#b51927] focus:ring-2 focus:ring-[#b51927]/25 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                />
            </label>

            <p
                v-if="error"
                class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:bg-rose-950/40 dark:text-rose-300"
            >
                {{ error }}
            </p>

            <button
                type="submit"
                :disabled="cargando"
                class="mt-2 h-13 w-full rounded-xl bg-[#b51927] text-lg font-bold text-white transition active:scale-[.98] disabled:opacity-60"
            >
                {{ cargando ? 'Entrando…' : 'Entrar' }}
            </button>
        </form>
    </div>
</template>
