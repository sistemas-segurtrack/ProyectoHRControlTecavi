<script setup lang="ts">
import { Download, Share } from '@lucide/vue';
import { onMounted, onUnmounted, ref } from 'vue';

// `BeforeInstallPromptEvent` no está en el lib.dom.d.ts estándar de
// TypeScript (es propietario de Chromium) — se tipa mínimo con lo que se usa.
type EventoInstalacion = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

// `public/recursos/logo-segurtrack.png` no pasa por Vite, así que necesita el
// mismo prefijo de subpath que el resto de la PWA (ver LoginPage.vue).
const logoUrl = `${import.meta.env.VITE_PWA_BASE_PATH ?? ''}/recursos/logo-segurtrack.png`;

// No existe una API estándar para preguntarle al navegador "¿ya la instalé
// antes?" en una pestaña normal (fuera de modo standalone) — se guarda el
// propio evento `appinstalled` en localStorage para reconocerlo en visitas
// futuras y no volver a ofrecer instalar algo que ya está instalado.
const LS_INSTALADA = 'pwa_instalada';

function marcarInstaladaLocal(): void {
    try {
        localStorage.setItem(LS_INSTALADA, '1');
    } catch {
        /* almacenamiento no disponible */
    }
}

function yaInstaladaLocal(): boolean {
    try {
        return localStorage.getItem(LS_INSTALADA) === '1';
    } catch {
        return false;
    }
}

let eventoDiferido: EventoInstalacion | null = null;

const puedeInstalar = ref(false);
const instalando = ref(false);
const instalado = ref(false);
const esIOS = ref(false);

function olvidarInstaladaLocal(): void {
    try {
        localStorage.removeItem(LS_INSTALADA);
    } catch {
        /* almacenamiento no disponible */
    }
}

function onBeforeInstallPrompt(e: Event): void {
    e.preventDefault();
    eventoDiferido = e as EventoInstalacion;
    puedeInstalar.value = true;
    // El navegador solo ofrece instalar si NO está instalada: la marca local
    // quedó vieja (se desinstaló).
    instalado.value = false;
    olvidarInstaladaLocal();
}

function onAppInstalled(): void {
    instalado.value = true;
    eventoDiferido = null;
    puedeInstalar.value = false;
    marcarInstaladaLocal();
}

onMounted(() => {
    esIOS.value =
        /iphone|ipad|ipod/i.test(navigator.userAgent) &&
        !('MSStream' in window);
    instalado.value = yaInstaladaLocal();

    window.addEventListener('beforeinstallprompt', onBeforeInstallPrompt);
    window.addEventListener('appinstalled', onAppInstalled);
});

onUnmounted(() => {
    window.removeEventListener('beforeinstallprompt', onBeforeInstallPrompt);
    window.removeEventListener('appinstalled', onAppInstalled);
});

async function instalar(): Promise<void> {
    if (!eventoDiferido) return;
    instalando.value = true;
    try {
        await eventoDiferido.prompt();
        const { outcome } = await eventoDiferido.userChoice;
        if (outcome === 'accepted') {
            instalado.value = true;
            marcarInstaladaLocal();
        }
    } finally {
        eventoDiferido = null;
        puedeInstalar.value = false;
        instalando.value = false;
    }
}
</script>

<template>
    <div class="flex flex-1 flex-col items-center justify-center gap-8 p-6">
        <img
            :src="logoUrl"
            alt="Segurtrack"
            class="w-56 max-w-[70%] dark:rounded-xl dark:bg-white dark:p-3"
        />

        <div class="flex flex-col items-center gap-2 text-center">
            <h1 class="text-xl font-bold">HRControl Tecavi</h1>
            <p class="max-w-xs text-sm text-gray-500 dark:text-gray-400">
                Esta app solo funciona instalada en tu celular. Instálala para
                usarla con su propio ícono, sin abrir el navegador.
            </p>
        </div>

        <div class="flex w-full flex-col gap-3">
            <button
                v-if="puedeInstalar"
                type="button"
                :disabled="instalando"
                class="flex h-14 w-full items-center justify-center gap-2 rounded-xl bg-[#b51927] text-lg font-bold text-white transition active:scale-[.98] disabled:opacity-60"
                @click="instalar"
            >
                <Download class="h-5 w-5" />
                {{ instalando ? 'Instalando…' : 'Instalar aplicativo' }}
            </button>

            <p
                v-else-if="instalado"
                class="rounded-xl bg-emerald-50 px-4 py-3 text-center text-sm font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300"
            >
                Ya está instalada en tu dispositivo — ábrela desde el ícono en
                tu pantalla de inicio.
            </p>

            <div
                v-else-if="esIOS"
                class="flex items-start gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
            >
                <Share class="mt-0.5 h-5 w-5 shrink-0 text-[#b51927]" />
                <span>
                    Toca <strong>Compartir</strong> y elige
                    <strong>«Agregar a pantalla de inicio»</strong> para
                    instalar la app.
                </span>
            </div>

            <p
                v-else
                class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400"
            >
                Busca «Instalar aplicación» o «Agregar a pantalla de inicio» en
                el menú del navegador.
            </p>
        </div>
    </div>
</template>
