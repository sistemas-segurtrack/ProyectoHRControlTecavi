<script setup lang="ts">
import { ChevronLeft } from '@lucide/vue';
import { ref, useTemplateRef } from 'vue';
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

const form = ref({
    placa: '',
    copiloto: '',
    precintos: '',
    carreta: '',
    geocerca: '',
    observacion: '',
    kilometraje: '',
});
const adjunto = useTemplateRef('adjunto');
const cargando = ref(false);
const error = ref('');
const idempotencyKey = uuid();

function cuerpo(): FormData | Record<string, string | null> {
    const base = {
        placa: form.value.placa.trim(),
        copiloto: form.value.copiloto.trim() || null,
        precintos: form.value.precintos.trim() || null,
        carreta: form.value.carreta.trim() || null,
        geocerca: form.value.geocerca.trim() || null,
        // La coordenada se toma automáticamente de la ubicación del dispositivo.
        coordenada: ubicacion.coordenada,
        observacion: form.value.observacion.trim() || null,
        kilometraje: form.value.kilometraje.trim() || null,
    };

    if (!adjunto.value?.activo) return base;

    const fd = new FormData();
    for (const [k, v] of Object.entries(base)) {
        if (v !== null) fd.append(k, v);
    }
    adjunto.value.anexar(fd);
    return fd;
}

async function iniciar(): Promise<void> {
    if (adjunto.value && !adjunto.value.listo) {
        error.value = 'Elige el tipo de documento.';
        return;
    }
    error.value = '';
    cargando.value = true;
    try {
        const res = await api<{ data: Ruta }>('/rutas', {
            method: 'POST',
            idempotencyKey,
            body: cuerpo(),
        });
        setRutaActiva(res.data);
        router.replace({ name: 'home' });
    } catch (e) {
        error.value =
            e instanceof ApiError ? e.message : 'No se pudo crear la ruta.';
    } finally {
        cargando.value = false;
    }
}
</script>

<template>
    <div class="flex flex-1 flex-col gap-5 p-6">
        <header class="flex items-center gap-3">
            <button
                class="rounded-lg p-1 text-gray-400"
                aria-label="Volver"
                @click="router.back()"
            >
                <ChevronLeft class="h-6 w-6" />
            </button>
            <h1 class="text-lg font-bold">Nueva hoja de ruta</h1>
        </header>

        <form class="flex flex-1 flex-col gap-4" @submit.prevent="iniciar">
            <CampoCombo
                v-model="form.placa"
                label="Placa"
                :options="state.catalogos.placas"
                placeholder="Selecciona la unidad"
                required
            />
            <CampoCombo
                v-model="form.copiloto"
                label="Copiloto"
                :options="state.catalogos.copilotos"
                placeholder="Opcional"
            />
            <CampoTexto
                v-model="form.precintos"
                label="Precintos"
                placeholder="Número de precintos"
            />
            <CampoCombo
                v-model="form.carreta"
                label="Carreta"
                :options="state.catalogos.carretas"
                placeholder="Opcional"
            />

            <CampoCombo
                v-model="form.geocerca"
                label="Lugar"
                :options="state.catalogos.geocercas"
                placeholder="Punto de partida"
            />

            <CampoTexto
                v-model="form.observacion"
                label="Observación"
                placeholder="Opcional"
            />

            <AdjuntarDocumento ref="adjunto" />

            <CampoTexto
                v-model="form.kilometraje"
                label="Kilometraje inicial"
                inputmode="numeric"
                placeholder="Km del odómetro"
            />

            <EstadoUbicacion />

            <p
                v-if="error"
                class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:bg-rose-950/40 dark:text-rose-300"
            >
                {{ error }}
            </p>

            <div class="mt-auto flex flex-col gap-2 pt-4">
                <p class="text-center text-xs text-gray-400">
                    Conductor: {{ state.conductor?.nombre }}
                </p>
                <button
                    type="submit"
                    :disabled="cargando"
                    class="h-14 w-full rounded-xl bg-[#b51927] text-lg font-bold text-white transition active:scale-[.98] disabled:opacity-60"
                >
                    {{ cargando ? 'Iniciando…' : 'Iniciar ruta' }}
                </button>
            </div>
        </form>
    </div>
</template>
