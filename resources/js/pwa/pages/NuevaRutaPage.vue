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
import {
    armarCuerpo,
    faltanteAntesDeEnviar,
    textoONulo,
} from '../lib/formularioAvance';
import { errorKilometraje } from '../lib/kilometraje';
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

// Parada 1: no hay parada anterior con qué comparar (solo entero y tope).
const errorKm = computed(() => errorKilometraje(form.value.kilometraje, null));

// Al elegir la placa se detecta al instante (con el catálogo ya cacheado,
// sin esperar al servidor) si esa unidad ya tiene un tramo abierto con
// OTRO conductor: no se puede crear una hoja desde cero encima, hay que
// continuarla o finalizarla primero. El servidor valida lo mismo por si
// las dudas (`CrearRutaRequest`).
const idRutaEnCurso = computed(
    () => state.catalogos.unidades_en_ruta?.[form.value.placa.trim()] ?? null,
);

async function iniciar(): Promise<void> {
    if (idRutaEnCurso.value) {
        error.value = `Esta unidad ya tiene una hoja de ruta en curso (${idRutaEnCurso.value}). Continúala o finalízala antes de iniciar una nueva.`;
        return;
    }
    const faltante = faltanteAntesDeEnviar({
        adjunto: adjunto.value,
        kilometraje: form.value.kilometraje,
        geocerca: form.value.geocerca,
    });
    if (faltante) {
        error.value = faltante;
        return;
    }
    // El aviso de km ya está visible junto al campo.
    if (errorKm.value) return;
    error.value = '';
    cargando.value = true;

    const campos = {
        placa: form.value.placa.trim(),
        copiloto: textoONulo(form.value.copiloto),
        precintos: textoONulo(form.value.precintos),
        carreta: textoONulo(form.value.carreta),
        geocerca: textoONulo(form.value.geocerca),
        // La coordenada se toma automáticamente de la ubicación del dispositivo.
        coordenada: ubicacion.coordenada,
        observacion: textoONulo(form.value.observacion),
        fhRegistro: form.value.fhRegistro || null,
        kilometraje: textoONulo(form.value.kilometraje),
    };
    const cuerpo = armarCuerpo(campos, adjunto.value);
    try {
        const res = await api<{ data: Ruta }>('/rutas', {
            method: 'POST',
            idempotencyKey,
            body: cuerpo,
        });
        // Si el documento adjunto finaliza la hoja de ruta, ya no queda "en
        // curso" — Home debe ofrecer "Nueva Ruta" y no "Continuar".
        setRutaActiva(rutaFinalizada(res.data) ? null : res.data);
        router.replace({ name: 'home' });
    } catch (e) {
        if (e instanceof ApiError && e.status === 0) {
            // Sin señal: se arma localmente y se manda cuando vuelva.
            const local = await encolarCrearRuta(cuerpo, {
                ...campos,
                piloto: state.conductor?.nombre ?? '',
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
                    Esta unidad ya tiene una hoja de ruta en curso ({{
                        idRutaEnCurso
                    }}). Continúala o finalízala antes de iniciar una nueva.
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
                required
            />

            <CampoTexto
                v-model="form.observacion"
                label="Observación"
                placeholder="Opcional"
            />

            <!-- Su orden siempre es 1 (impar, abre el primer tramo): un
                 documento que finaliza la hoja (RECIBO COMBUSTIBLE) no
                 tiene sentido todavía. -->
            <AdjuntarDocumento ref="adjunto" ocultar-finalizadores />

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
