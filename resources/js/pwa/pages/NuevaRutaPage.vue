<script setup lang="ts">
import { ChevronLeft } from '@lucide/vue';
import { computed, ref, useTemplateRef } from 'vue';
import { useRouter } from 'vue-router';
import AdjuntarDocumento from '../components/AdjuntarDocumento.vue';
import CampoCombo from '../components/CampoCombo.vue';
import CampoTexto from '../components/CampoTexto.vue';
import EstadoUbicacion from '../components/EstadoUbicacion.vue';
import { api, ApiError, fechaHoraLocal, uuid } from '../lib/api';
import { useUbicacion } from '../lib/dispositivo';
import { errorKilometraje, referenciaKilometraje } from '../lib/kilometraje';
import { encolarCrearRuta } from '../lib/sincronizar';
import { rutaFinalizada, useAuth, type Ruta } from '../stores/auth';

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
    fhRegistro: fechaHoraLocal(),
    kilometraje: '',
});
const adjunto = useTemplateRef('adjunto');
const cargando = ref(false);
const error = ref('');
const idempotencyKey = uuid();

// Al iniciar una hoja de ruta no se compara contra el contador de Wialon
// (puede estar desactualizado y rechazar de arranque un kilometraje real
// válido) — por eso no se le pasa la placa, igual que ya no lo hace el
// servidor (CrearRutaRequest).
const referenciaKm = computed(() =>
    referenciaKilometraje(state.catalogos, null),
);
const errorKm = computed(() =>
    errorKilometraje(form.value.kilometraje, referenciaKm.value),
);

// Al elegir la placa se detecta al instante (con el catálogo ya cacheado,
// sin esperar al servidor) si esa unidad ya tiene un tramo abierto con
// OTRO conductor: no se puede crear una hoja desde cero encima, hay que
// continuarla o finalizarla primero. El servidor valida lo mismo por si
// las dudas (`CrearRutaRequest`).
const idRutaEnCurso = computed(
    () => state.catalogos.unidades_en_ruta?.[form.value.placa.trim()] ?? null,
);

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
        fhRegistro: form.value.fhRegistro || null,
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
    if (idRutaEnCurso.value) {
        error.value = `Esta unidad ya tiene una hoja de ruta en curso (${idRutaEnCurso.value}). Continúala o finalízala antes de iniciar una nueva.`;
        return;
    }
    if (adjunto.value && !adjunto.value.listo) {
        error.value =
            'Completa el tipo, el código y la foto del documento adjunto.';
        return;
    }
    if (form.value.kilometraje.trim() === '') {
        error.value = 'El kilometraje es obligatorio.';
        return;
    }
    // El aviso ya está visible junto al campo (se actualiza al instante
    // mientras se escribe) — no hace falta duplicarlo en el banner general.
    if (errorKm.value) return;
    error.value = '';
    cargando.value = true;
    const cuerpoArmado = cuerpo();
    try {
        const res = await api<{ data: Ruta }>('/rutas', {
            method: 'POST',
            idempotencyKey,
            body: cuerpoArmado,
        });
        // Si el documento adjunto finaliza la hoja de ruta (p. ej. un recibo
        // de combustible), ya no queda "en curso" — Home debe ofrecer
        // "Nueva Ruta" y no "Continuar" sobre algo que ya terminó.
        setRutaActiva(rutaFinalizada(res.data) ? null : res.data);
        router.replace({ name: 'home' });
    } catch (e) {
        if (e instanceof ApiError && e.status === 0) {
            // Sin señal: se arma localmente y se manda cuando vuelva.
            const local = await encolarCrearRuta(cuerpoArmado, {
                placa: form.value.placa.trim(),
                piloto: state.conductor?.nombre ?? '',
                copiloto: form.value.copiloto.trim() || null,
                precintos: form.value.precintos.trim() || null,
                carreta: form.value.carreta.trim() || null,
                geocerca: form.value.geocerca.trim() || null,
                coordenada: ubicacion.coordenada,
                kilometraje: form.value.kilometraje.trim() || null,
                fhRegistro: form.value.fhRegistro,
                finaliza: adjunto.value?.finalizara ?? false,
            });
            setRutaActiva(rutaFinalizada(local) ? null : local);
            router.replace({ name: 'home' });
            return;
        }
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
            <div class="flex flex-col gap-1">
                <CampoCombo
                    v-model="form.placa"
                    label="Placa"
                    :options="state.catalogos.placas"
                    placeholder="Selecciona la unidad"
                    required
                />
                <p
                    v-if="idRutaEnCurso"
                    class="text-xs text-rose-600 dark:text-rose-400"
                >
                    Esta unidad ya tiene una hoja de ruta en curso
                    ({{ idRutaEnCurso }}). Continúala o finalízala antes de
                    iniciar una nueva.
                </p>
            </div>
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
                v-model="form.fhRegistro"
                label="Fecha y hora"
                type="datetime-local"
                required
            />
            <div class="flex flex-col gap-1">
                <CampoTexto
                    v-model="form.kilometraje"
                    label="Kilometraje inicial"
                    inputmode="numeric"
                    placeholder="Km del odómetro"
                    required
                />
                <p
                    v-if="errorKm"
                    class="text-xs text-rose-600 dark:text-rose-400"
                >
                    {{ errorKm }}
                </p>
            </div>

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
                    :disabled="cargando || !!errorKm || !!idRutaEnCurso"
                    class="h-14 w-full rounded-xl bg-[#b51927] text-lg font-bold text-white transition active:scale-[.98] disabled:opacity-60"
                >
                    {{ cargando ? 'Iniciando…' : 'Iniciar ruta' }}
                </button>
            </div>
        </form>
    </div>
</template>
