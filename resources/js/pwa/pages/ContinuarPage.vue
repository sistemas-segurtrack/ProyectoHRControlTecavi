<script setup lang="ts">
import { ChevronLeft, Paperclip } from '@lucide/vue';
import { computed, onMounted, ref, useTemplateRef } from 'vue';
import { useRouter } from 'vue-router';
import AdjuntarDocumento from '../components/AdjuntarDocumento.vue';
import CampoCombo from '../components/CampoCombo.vue';
import CampoTexto from '../components/CampoTexto.vue';
import EstadoUbicacion from '../components/EstadoUbicacion.vue';
import { api, ApiError, uuid } from '../lib/api';
import { useUbicacion } from '../lib/dispositivo';
import { useAuth, type Ruta } from '../stores/auth';

const router = useRouter();
const { state, setRutaActiva } = useAuth();
const { ubicacion } = useUbicacion();

onMounted(() => {
    if (!state.rutaActiva) {
        router.replace({ name: 'home' });
    }
});

const ruta = computed(() => state.rutaActiva);

const form = ref({
    geocerca: '',
    kilometraje: '',
    observacion: '',
});
const adjunto = useTemplateRef('adjunto');
const cargando = ref(false);
const error = ref('');

function limpiar(): void {
    form.value = { geocerca: '', kilometraje: '', observacion: '' };
    adjunto.value?.reset();
}

function cuerpo(): FormData | Record<string, string | null> {
    const base = {
        geocerca: form.value.geocerca.trim() || null,
        // Coordenada automática de la ubicación del dispositivo.
        coordenada: ubicacion.coordenada,
        kilometraje: form.value.kilometraje.trim() || null,
        observacion: form.value.observacion.trim() || null,
    };

    if (!adjunto.value?.activo) return base;

    const fd = new FormData();
    for (const [k, v] of Object.entries(base)) {
        if (v !== null) fd.append(k, v);
    }
    adjunto.value.anexar(fd);
    return fd;
}

async function registrar(): Promise<void> {
    if (!ruta.value) return;
    if (adjunto.value && !adjunto.value.listo) {
        error.value = 'Elige el tipo de documento.';
        return;
    }
    error.value = '';
    cargando.value = true;
    try {
        const res = await api<{ data: Ruta }>(
            `/rutas/${ruta.value.idruta}/ordenes`,
            { method: 'POST', idempotencyKey: uuid(), body: cuerpo() },
        );
        setRutaActiva(res.data);
        limpiar();
        router.replace({ name: 'home' });
    } catch (e) {
        error.value =
            e instanceof ApiError ? e.message : 'No se pudo registrar.';
    } finally {
        cargando.value = false;
    }
}
</script>

<template>
    <div v-if="ruta" class="flex flex-1 flex-col gap-5 p-6">
        <header class="flex items-center gap-3">
            <button
                class="rounded-lg p-1 text-gray-400"
                aria-label="Volver"
                @click="router.back()"
            >
                <ChevronLeft class="h-6 w-6" />
            </button>
            <div>
                <h1 class="text-lg font-bold">Continuar ruta</h1>
                <p class="font-mono text-xs text-gray-400">{{ ruta.idruta }}</p>
            </div>
        </header>

        <!-- Órdenes registradas -->
        <div class="flex flex-col gap-2">
            <p class="text-xs font-bold tracking-wide text-gray-500 uppercase">
                Avances ({{ ruta.ordenes.length }})
            </p>
            <ul class="flex flex-col gap-1.5">
                <li
                    v-for="o in ruta.ordenes"
                    :key="o.id"
                    class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900"
                >
                    <span class="font-semibold">#{{ o.orden }}</span>
                    <span class="text-gray-500">{{ o.geocerca ?? '—' }}</span>
                    <Paperclip
                        v-if="o.documentos.length"
                        class="h-3.5 w-3.5 text-gray-400"
                    />

                    <span
                        class="rounded-full px-2 py-0.5 text-[11px] font-bold"
                        :class="
                            o.estado === 'ER'
                                ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300'
                                : 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300'
                        "
                    >
                        {{ o.estado_label }}
                    </span>
                </li>
            </ul>
        </div>

        <form class="flex flex-1 flex-col gap-4" @submit.prevent="registrar">
            <p class="text-xs font-bold tracking-wide text-gray-500 uppercase">
                Registrar avance
            </p>

            <CampoCombo
                v-model="form.geocerca"
                label="Lugar"
                :options="state.catalogos.geocercas"
                placeholder="Dónde estás"
            />

            <CampoTexto
                v-model="form.kilometraje"
                label="Kilometraje"
                inputmode="numeric"
                placeholder="Km del odómetro"
            />
            <CampoTexto
                v-model="form.observacion"
                label="Observación"
                placeholder="Opcional"
            />

            <AdjuntarDocumento ref="adjunto" />

            <EstadoUbicacion />

            <p
                v-if="error"
                class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:bg-rose-950/40 dark:text-rose-300"
            >
                {{ error }}
            </p>

            <button
                type="submit"
                :disabled="cargando"
                class="mt-auto h-14 w-full rounded-xl bg-[#b51927] text-lg font-bold text-white transition active:scale-[.98] disabled:opacity-60"
            >
                {{ cargando ? 'Registrando…' : 'Registrar avance' }}
            </button>
        </form>
    </div>
</template>
